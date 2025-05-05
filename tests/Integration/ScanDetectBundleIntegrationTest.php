<?php

namespace Tourze\ScanDetectBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber;

/**
 * ScanDetectBundle 集成测试
 */
class ScanDetectBundleIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return IntegrationTestKernel::class;
    }

    /**
     * 测试Bundle是否正确注册
     */
    public function testBundleRegistration(): void
    {
        $kernel = self::bootKernel();

        // 验证 Bundle 已正确注册
        $this->assertTrue($kernel->getBundle('ScanDetectBundle') !== null, 'ScanDetectBundle 未正确注册');

        // 验证缓存服务是否可用
        $cache = self::getContainer()->get(\Psr\SimpleCache\CacheInterface::class);
        $this->assertInstanceOf(\Psr\SimpleCache\CacheInterface::class, $cache);
    }

    /**
     * 测试订阅者的onKernelRequest方法集成
     */
    public function testOnKernelRequestIntegration(): void
    {
        $kernel = self::bootKernel();

        // 手动创建订阅者
        $cache = self::getContainer()->get(\Psr\SimpleCache\CacheInterface::class);
        $subscriber = new ScanDetect404Subscriber($cache);

        // 创建请求事件
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        // 执行请求处理
        $subscriber->onKernelRequest($event);

        // 正常情况下不应设置响应
        $this->assertNull($event->getResponse());
    }

    /**
     * 测试订阅者的onKernelException方法集成
     */
    public function testOnKernelExceptionIntegration(): void
    {
        $kernel = self::bootKernel();

        // 手动创建订阅者
        $cache = self::getContainer()->get(\Psr\SimpleCache\CacheInterface::class);
        $subscriber = new ScanDetect404Subscriber($cache);

        // 创建异常事件
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $exception = new NotFoundHttpException('页面未找到');
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        // 执行异常处理
        $subscriber->onKernelException($event);

        // 异常应被正常处理（这里只能验证不抛异常）
        $this->assertTrue(true);
    }

    /**
     * 测试多次404请求后拒绝恶意IP的完整流程
     */
    public function testMultiple404RequestsBlocking(): void
    {
        // 设置环境变量为较小值，方便测试
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = 3;

        $kernel = self::bootKernel();

        // 获取缓存实例，手动创建订阅者
        $cache = self::getContainer()->get(\Psr\SimpleCache\CacheInterface::class);
        $subscriber = new ScanDetect404Subscriber($cache);

        // 创建IP和异常
        $ip = '192.168.1.100';
        $request = Request::create('https://example.com');
        $request->server->set('REMOTE_ADDR', $ip);
        $exception = new NotFoundHttpException('页面未找到');

        // 模拟多次404请求
        for ($i = 0; $i < 5; $i++) {
            $exceptionEvent = new ExceptionEvent(
                $kernel,
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $exception
            );
            $subscriber->onKernelException($exceptionEvent);
        }

        // 检查下一次请求是否被阻止
        $requestEvent = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
        $subscriber->onKernelRequest($requestEvent);

        // 应该返回403响应
        $response = $requestEvent->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('ScanForbidden', $response->getContent());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // 清理环境变量
        if (isset($_ENV['SCAN_DETECT_404_FOUND_TIME'])) {
            unset($_ENV['SCAN_DETECT_404_FOUND_TIME']);
        }
    }
}
