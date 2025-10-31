<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber;
use Tourze\ScanDetectBundle\Service\ScanDetectService;

/**
 * ScanDetect404Subscriber 单元测试
 *
 * 测试扫描检测404事件订阅器的核心功能：
 * - 阻止已被标记的恶意IP访问
 * - 记录404异常并触发IP封禁检查
 * - 对安全IP的白名单处理
 *
 * @internal
 */
#[CoversClass(ScanDetect404Subscriber::class)]
#[RunTestsInSeparateProcesses]
final class ScanDetect404SubscriberTest extends AbstractEventSubscriberTestCase
{
    private ScanDetect404Subscriber $subscriber;

    private ScanDetectService&MockObject $scanDetectService;

    private HttpKernelInterface&MockObject $mockKernel;

    protected function onSetUp(): void
    {
        $this->scanDetectService = $this->createMock(ScanDetectService::class);
        $this->mockKernel = $this->createMock(HttpKernelInterface::class);

        // 将Mock服务注入到容器中，以便getService能够返回Mock对象
        self::getContainer()->set(ScanDetectService::class, $this->scanDetectService);

        $this->subscriber = self::getService(ScanDetect404Subscriber::class);
    }

    public function testSubscriberListensToCorrectEvents(): void
    {
        // 通过反射验证事件监听器属性
        $reflection = new \ReflectionClass(ScanDetect404Subscriber::class);

        // 检查 onKernelRequest 方法的事件监听器属性
        $requestMethod = $reflection->getMethod('onKernelRequest');
        $requestAttributes = $requestMethod->getAttributes();
        $this->assertNotEmpty($requestAttributes);

        // 检查 onKernelException 方法的事件监听器属性
        $exceptionMethod = $reflection->getMethod('onKernelException');
        $exceptionAttributes = $exceptionMethod->getAttributes();
        $this->assertNotEmpty($exceptionAttributes);
    }

    public function testOnKernelRequestBlocksIPWhenBlocked(): void
    {
        $request = $this->createRequestWithIp('192.168.1.100');
        $event = new RequestEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // 模拟IP被封禁
        $this->scanDetectService
            ->expects($this->once())
            ->method('isIPBlocked')
            ->with('192.168.1.100')
            ->willReturn(true)
        ;

        $this->subscriber->onKernelRequest($event);

        // 验证响应被设置为403禁止访问
        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('ScanForbidden', $response->getContent());

        // 验证事件传播被停止
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testOnKernelRequestAllowsIPWhenNotBlocked(): void
    {
        $request = $this->createRequestWithIp('192.168.1.100');
        $event = new RequestEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // 模拟IP未被封禁
        $this->scanDetectService
            ->expects($this->once())
            ->method('isIPBlocked')
            ->with('192.168.1.100')
            ->willReturn(false)
        ;

        $this->subscriber->onKernelRequest($event);

        // 验证没有设置响应
        $this->assertNull($event->getResponse());

        // 验证事件传播未被停止
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testOnKernelRequestIgnoresRequestsWithNoClientIP(): void
    {
        $request = $this->createRequestWithIp(null);
        $event = new RequestEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // 不应该调用任何扫描检测方法
        $this->scanDetectService
            ->expects($this->never())
            ->method('isIPBlocked')
        ;

        $this->subscriber->onKernelRequest($event);

        // 验证没有设置响应
        $this->assertNull($event->getResponse());

        // 验证事件传播未被停止
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testOnKernelExceptionRecordsScanAttemptForNotFoundHttpException(): void
    {
        $request = $this->createRequestWithIp('192.168.1.100');
        $exception = new NotFoundHttpException('Page not found');
        $event = new ExceptionEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        // 验证扫描尝试被记录
        $this->scanDetectService
            ->expects($this->once())
            ->method('recordScanAttempt')
            ->with($request, 404)
        ;

        // 验证检查并封禁IP被调用
        $this->scanDetectService
            ->expects($this->once())
            ->method('checkAndBlockIP')
            ->with('192.168.1.100')
        ;

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionIgnoresNonNotFoundHttpException(): void
    {
        $request = $this->createRequestWithIp('192.168.1.100');
        $exception = new \RuntimeException('Some other error');
        $event = new ExceptionEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        // 不应该记录扫描尝试
        $this->scanDetectService
            ->expects($this->never())
            ->method('recordScanAttempt')
        ;

        // 不应该检查IP封禁
        $this->scanDetectService
            ->expects($this->never())
            ->method('checkAndBlockIP')
        ;

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionIgnoresRequestsWithNoClientIP(): void
    {
        $request = $this->createRequestWithIp(null);
        $exception = new NotFoundHttpException('Page not found');
        $event = new ExceptionEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        // 不应该记录扫描尝试
        $this->scanDetectService
            ->expects($this->never())
            ->method('recordScanAttempt')
        ;

        // 不应该检查IP封禁
        $this->scanDetectService
            ->expects($this->never())
            ->method('checkAndBlockIP')
        ;

        $this->subscriber->onKernelException($event);
    }

    #[DataProvider('ipAddressProvider')]
    public function testOnKernelRequestHandlesDifferentIPTypes(?string $ip, bool $shouldCallService): void
    {
        $request = $this->createRequestWithIp($ip);
        $event = new RequestEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST);

        if ($shouldCallService) {
            $this->scanDetectService
                ->expects($this->once())
                ->method('isIPBlocked')
                ->with($ip)
                ->willReturn(false)
            ;
        } else {
            $this->scanDetectService
                ->expects($this->never())
                ->method('isIPBlocked')
            ;
        }

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    #[DataProvider('ipAddressProvider')]
    public function testOnKernelExceptionHandlesDifferentIPTypes(?string $ip, bool $shouldCallService): void
    {
        $request = $this->createRequestWithIp($ip);
        $exception = new NotFoundHttpException('Page not found');
        $event = new ExceptionEvent($this->mockKernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        if ($shouldCallService) {
            $this->scanDetectService
                ->expects($this->once())
                ->method('recordScanAttempt')
                ->with($request, 404)
            ;

            $this->scanDetectService
                ->expects($this->once())
                ->method('checkAndBlockIP')
                ->with($ip)
            ;
        } else {
            $this->scanDetectService
                ->expects($this->never())
                ->method('recordScanAttempt')
            ;

            $this->scanDetectService
                ->expects($this->never())
                ->method('checkAndBlockIP')
            ;
        }

        $this->subscriber->onKernelException($event);
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function ipAddressProvider(): array
    {
        return [
            'valid IPv4' => ['192.168.1.100', true],
            'valid IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334', true],
            'localhost IPv4' => ['127.0.0.1', true],
            'localhost IPv6' => ['::1', true],
            'null IP' => [null, false],
        ];
    }

    public function testSubscriberCanBeInstantiated(): void
    {
        $subscriber = self::getService(ScanDetect404Subscriber::class);
        $this->assertInstanceOf(ScanDetect404Subscriber::class, $subscriber);
    }

    /**
     * 创建带有指定客户端IP的请求对象
     */
    private function createRequestWithIp(?string $ip): Request
    {
        $request = new Request();

        if (null !== $ip) {
            // 设置客户端IP，模拟真实请求场景
            $request->server->set('REMOTE_ADDR', $ip);
            $request->headers->set('X-Forwarded-For', $ip);
        }

        return $request;
    }
}
