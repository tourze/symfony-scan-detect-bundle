<?php

namespace Tourze\ScanDetectBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber;

/**
 * ScanDetect404Subscriber 单元测试
 *
 * 测试扫描检测功能，包括请求处理和异常处理
 */
class ScanDetect404SubscriberTest extends TestCase
{
    private CacheInterface $cache;
    private ScanDetect404Subscriber $subscriber;
    private KernelInterface $kernel;
    private ?string $originalEnvValue = null;

    protected function setUp(): void
    {
        // 保存原始环境变量值
        $this->originalEnvValue = $_ENV['SCAN_DETECT_404_FOUND_TIME'] ?? null;

        // 默认设置扫描检测阈值为20
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = 20;

        // 模拟缓存接口
        $this->cache = $this->createMock(CacheInterface::class);

        // 创建订阅器实例
        $this->subscriber = new ScanDetect404Subscriber($this->cache);

        // 模拟内核
        $this->kernel = $this->createMock(KernelInterface::class);
    }

    protected function tearDown(): void
    {
        // 恢复原始环境变量值
        if ($this->originalEnvValue === null) {
            unset($_ENV['SCAN_DETECT_404_FOUND_TIME']);
        } else {
            $_ENV['SCAN_DETECT_404_FOUND_TIME'] = $this->originalEnvValue;
        }
    }

    /**
     * 测试当maxTime小于等于零时onKernelRequest方法不做任何处理
     */
    public function testOnKernelRequest_withMaxTimeLessThanZero(): void
    {
        // 设置环境变量为0
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = 0;

        // 创建请求事件
        $request = Request::create('https://example.com');
        $event = $this->createRequestEvent($request);

        // 缓存不应被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 执行测试
        $this->subscriber->onKernelRequest($event);

        // 验证没有响应被设置
        $this->assertNull($event->getResponse());
    }

    /**
     * 测试使用安全IP时onKernelRequest方法不会进行任何检查
     */
    public function testOnKernelRequest_withSafeIP(): void
    {
        // 创建使用安全IP的请求
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $event = $this->createRequestEvent($request);

        // 缓存不应被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 执行测试
        $this->subscriber->onKernelRequest($event);

        // 验证没有响应被设置
        $this->assertNull($event->getResponse());
    }

    /**
     * 测试当IP被标记为恶意时onKernelRequest方法会返回403响应
     */
    public function testOnKernelRequest_withAccessDeniedIP(): void
    {
        // 创建请求
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $event = $this->createRequestEvent($request);

        // 模拟缓存返回时间戳，表示IP已被禁止
        $this->cache->expects($this->once())
            ->method('get')
            ->with('ACCESS_DENIED_192.168.1.1')
            ->willReturn(time());

        // 执行测试
        $this->subscriber->onKernelRequest($event);

        // 验证是否设置了403响应
        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('ScanForbidden', $response->getContent());
    }

    /**
     * 测试当IP未被标记为恶意时onKernelRequest方法正常处理请求
     */
    public function testOnKernelRequest_withNormalIP(): void
    {
        // 创建请求
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $event = $this->createRequestEvent($request);

        // 模拟缓存返回0，表示IP未被禁止
        $this->cache->expects($this->once())
            ->method('get')
            ->with('ACCESS_DENIED_192.168.1.1')
            ->willReturn(0);

        // 执行测试
        $this->subscriber->onKernelRequest($event);

        // 验证没有响应被设置
        $this->assertNull($event->getResponse());
    }

    /**
     * 测试当异常不是NotFoundHttpException时onKernelException方法不进行处理
     */
    public function testOnKernelException_withNonNotFoundException(): void
    {
        // 创建异常事件，使用非NotFoundHttpException异常
        $request = Request::create('https://example.com');
        $exception = new \RuntimeException('一般运行时异常');
        $event = $this->createExceptionEvent($request, $exception);

        // 缓存不应被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 执行测试
        $this->subscriber->onKernelException($event);
    }

    /**
     * 测试使用安全IP时onKernelException方法不进行处理
     */
    public function testOnKernelException_withSafeIP(): void
    {
        // 创建异常事件，使用安全IP
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $exception = new NotFoundHttpException('页面未找到');
        $event = $this->createExceptionEvent($request, $exception);

        // 缓存不应被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 执行测试
        $this->subscriber->onKernelException($event);
    }

    /**
     * 测试当maxTime小于等于零时onKernelException方法不进行处理
     */
    public function testOnKernelException_withLowMaxTime(): void
    {
        // 设置环境变量为0
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = 0;

        // 创建异常事件
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $exception = new NotFoundHttpException('页面未找到');
        $event = $this->createExceptionEvent($request, $exception);

        // 缓存不应被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 执行测试
        $this->subscriber->onKernelException($event);
    }

    /**
     * 测试IP在短时间内触发大量404时会被标记为恶意IP
     */
    public function testOnKernelException_withFrequent404s(): void
    {
        // 创建异常事件
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $exception = new NotFoundHttpException('页面未找到');
        $event = $this->createExceptionEvent($request, $exception);

        // 设置环境变量
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = 5;

        // 模拟缓存返回超过阈值的计数
        $this->cache->expects($this->once())
            ->method('get')
            ->with('scan_detect_404_192.168.1.1', 0)
            ->willReturn(6);

        // 使用 at() 方法替代 withConsecutive 
        $this->cache->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($key, $value, $ttl = null) {
                static $callIndex = 0;

                if ($callIndex === 0) {
                    $this->assertEquals('ACCESS_DENIED_192.168.1.1', $key);
                    $this->assertIsInt($value);
                    $this->assertEquals(60 * 5, $ttl);
                } elseif ($callIndex === 1) {
                    $this->assertEquals('scan_detect_404_192.168.1.1', $key);
                    $this->assertEquals(7, $value);
                    $this->assertEquals(60, $ttl);
                }

                $callIndex++;
                return true;
            });

        // 执行测试
        $this->subscriber->onKernelException($event);
    }

    /**
     * 测试IP触发404但未达到阈值时的计数器更新
     */
    public function testOnKernelException_withNonFrequent404s(): void
    {
        // 创建异常事件
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $exception = new NotFoundHttpException('页面未找到');
        $event = $this->createExceptionEvent($request, $exception);

        // 模拟缓存返回未超过阈值的计数
        $this->cache->expects($this->once())
            ->method('get')
            ->with('scan_detect_404_192.168.1.1', 0)
            ->willReturn(5);

        // 验证只更新计数器而不设置ACCESS_DENIED键
        $this->cache->expects($this->once())
            ->method('set')
            ->with('scan_detect_404_192.168.1.1', 6, 60);

        // 执行测试
        $this->subscriber->onKernelException($event);
    }

    /**
     * 测试当scanDetectKey为null时onKernelException不进行处理
     */
    public function testOnKernelException_withNullIP(): void
    {
        // 创建异常事件，IP为null
        $request = Request::create('https://example.com');
        $request->server->remove('REMOTE_ADDR');
        $exception = new NotFoundHttpException('页面未找到');
        $event = $this->createExceptionEvent($request, $exception);

        // 缓存的get不应被调用
        $this->cache->expects($this->never())
            ->method('get');

        // 执行测试
        $this->subscriber->onKernelException($event);
    }

    /**
     * 创建请求事件对象
     */
    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    /**
     * 创建异常事件对象
     */
    private function createExceptionEvent(Request $request, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
    }
}
