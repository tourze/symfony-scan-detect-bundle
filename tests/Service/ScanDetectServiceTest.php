<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Tourze\ScanDetectBundle\Service\ScanDetectService;

/**
 * ScanDetectService 单元测试
 *
 * 测试基于缓存的扫描检测服务核心功能：
 * - IP封禁状态检查和管理
 * - 扫描尝试记录和计数
 * - IP封禁逻辑和阈值处理
 * - 安全IP白名单机制
 * - 缓存操作的正确性
 *
 * @internal
 */
#[CoversClass(ScanDetectService::class)]
final class ScanDetectServiceTest extends TestCase
{
    private ScanDetectService $service;

    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->service = new ScanDetectService($this->cache);
    }

    public function testIsIPBlockedReturnsTrueWhenIPIsBlocked(): void
    {
        $ipAddress = '192.168.1.100';

        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('scan_detect_blocked_' . $ipAddress)
            ->willReturn(true)
        ;

        $result = $this->service->isIPBlocked($ipAddress);

        $this->assertTrue($result);
    }

    public function testIsIPBlockedReturnsFalseWhenIPIsNotBlocked(): void
    {
        $ipAddress = '192.168.1.100';

        $this->cache
            ->expects($this->once())
            ->method('has')
            ->with('scan_detect_blocked_' . $ipAddress)
            ->willReturn(false)
        ;

        $result = $this->service->isIPBlocked($ipAddress);

        $this->assertFalse($result);
    }

    #[DataProvider('safeIPProvider')]
    public function testIsIPBlockedReturnsFalseForSafeIPs(string $safeIP): void
    {
        // 安全IP不应该调用缓存检查
        $this->cache
            ->expects($this->never())
            ->method('has')
        ;

        $result = $this->service->isIPBlocked($safeIP);

        $this->assertFalse($result);
    }

    public function testRecordScanAttemptIncrementsErrorCount(): void
    {
        $ipAddress = '192.168.1.100';
        $request = $this->createRequestWithIP($ipAddress);

        // 模拟当前计数为5
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('scan_detect_count_' . $ipAddress, 0)
            ->willReturn(5)
        ;

        // 验证计数增加到6，并设置60秒过期时间
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('scan_detect_count_' . $ipAddress, 6, 60)
        ;

        $this->service->recordScanAttempt($request, 404);
    }

    public function testRecordScanAttemptHandlesFirstAttempt(): void
    {
        $ipAddress = '192.168.1.100';
        $request = $this->createRequestWithIP($ipAddress);

        // 模拟首次尝试，缓存返回默认值0
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('scan_detect_count_' . $ipAddress, 0)
            ->willReturn(0)
        ;

        // 验证计数设置为1
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('scan_detect_count_' . $ipAddress, 1, 60)
        ;

        $this->service->recordScanAttempt($request, 404);
    }

    #[DataProvider('safeIPProvider')]
    public function testRecordScanAttemptIgnoresSafeIPs(string $safeIP): void
    {
        $request = $this->createRequestWithIP($safeIP);

        // 安全IP不应该调用任何缓存方法
        $this->cache
            ->expects($this->never())
            ->method('get')
        ;

        $this->cache
            ->expects($this->never())
            ->method('set')
        ;

        $this->service->recordScanAttempt($request, 404);
    }

    public function testRecordScanAttemptIgnoresNullIP(): void
    {
        $request = $this->createRequestWithIP(null);

        // null IP不应该调用任何缓存方法
        $this->cache
            ->expects($this->never())
            ->method('get')
        ;

        $this->cache
            ->expects($this->never())
            ->method('set')
        ;

        $this->service->recordScanAttempt($request, 404);
    }

    public function testCheckAndBlockIPBlocksWhenThresholdExceeded(): void
    {
        $ipAddress = '192.168.1.100';

        // 模拟错误计数为25（超过默认阈值20）
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('scan_detect_count_' . $ipAddress, 0)
            ->willReturn(25)
        ;

        // 验证IP被封禁，设置300秒过期时间
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('scan_detect_blocked_' . $ipAddress, self::isInt(), 300)
        ;

        $result = $this->service->checkAndBlockIP($ipAddress);

        $this->assertTrue($result);
    }

    public function testCheckAndBlockIPDoesNotBlockWhenThresholdNotExceeded(): void
    {
        $ipAddress = '192.168.1.100';

        // 模拟错误计数为10（未超过默认阈值20）
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('scan_detect_count_' . $ipAddress, 0)
            ->willReturn(10)
        ;

        // 不应该设置封禁
        $this->cache
            ->expects($this->never())
            ->method('set')
        ;

        $result = $this->service->checkAndBlockIP($ipAddress);

        $this->assertFalse($result);
    }

    #[DataProvider('safeIPProvider')]
    public function testCheckAndBlockIPIgnoresSafeIPs(string $safeIP): void
    {
        // 安全IP不应该调用任何缓存方法
        $this->cache
            ->expects($this->never())
            ->method('get')
        ;

        $this->cache
            ->expects($this->never())
            ->method('set')
        ;

        $result = $this->service->checkAndBlockIP($safeIP);

        $this->assertFalse($result);
    }

    public function testCheckAndBlockIPRespectsEnvironmentVariableThreshold(): void
    {
        $ipAddress = '192.168.1.100';

        // 设置环境变量阈值为5
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = '5';

        // 模拟错误计数为6（超过环境变量阈值5）
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('scan_detect_count_' . $ipAddress, 0)
            ->willReturn(6)
        ;

        // 验证IP被封禁
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('scan_detect_blocked_' . $ipAddress, self::isInt(), 300)
        ;

        $result = $this->service->checkAndBlockIP($ipAddress);

        $this->assertTrue($result);

        // 清理环境变量
        unset($_ENV['SCAN_DETECT_404_FOUND_TIME']);
    }

    public function testUnblockIPRemovesBlockFromCache(): void
    {
        $ipAddress = '192.168.1.100';

        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with('scan_detect_blocked_' . $ipAddress)
            ->willReturn(true)
        ;

        $result = $this->service->unblockIP($ipAddress);

        $this->assertTrue($result);
    }

    public function testGetErrorCountReturnsCorrectCount(): void
    {
        $ipAddress = '192.168.1.100';
        $expectedCount = 15;

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('scan_detect_count_' . $ipAddress, 0)
            ->willReturn($expectedCount)
        ;

        $result = $this->service->getErrorCount($ipAddress);

        $this->assertSame($expectedCount, $result);
    }

    #[DataProvider('safeIPProvider')]
    public function testGetErrorCountReturnsZeroForSafeIPs(string $safeIP): void
    {
        // 安全IP不应该调用缓存
        $this->cache
            ->expects($this->never())
            ->method('get')
        ;

        $result = $this->service->getErrorCount($safeIP);

        $this->assertSame(0, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeIPProvider(): array
    {
        return [
            'localhost IPv4' => ['127.0.0.1'],
            'localhost IPv6' => ['::1'],
        ];
    }

    public function testResetErrorCountDeletesCountFromCache(): void
    {
        $ipAddress = '192.168.1.100';

        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with('scan_detect_count_' . $ipAddress)
            ->willReturn(true)
        ;

        $result = $this->service->resetErrorCount($ipAddress);

        $this->assertTrue($result);
    }

    public function testCleanupExpiredBlocksReturnsZero(): void
    {
        // Cache-based架构下，此方法应该返回0（兼容性）
        $result = $this->service->cleanupExpiredBlocks();

        $this->assertSame(0, $result);
    }

    public function testCleanupOldRecordsReturnsZero(): void
    {
        // Cache-based架构下，此方法应该返回0（兼容性）
        $result = $this->service->cleanupOldRecords(30);

        $this->assertSame(0, $result);
    }

    #[DataProvider('maxAttemptsProvider')]
    public function testMaxAttemptsFromEnvironment(string $envValue, int $expectedValue): void
    {
        $_ENV['SCAN_DETECT_404_FOUND_TIME'] = $envValue;

        $ipAddress = '192.168.1.100';
        $errorCount = $expectedValue + 1; // 超过阈值1

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn($errorCount)
        ;

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('scan_detect_blocked_' . $ipAddress, self::isInt(), 300)
        ;

        $result = $this->service->checkAndBlockIP($ipAddress);

        $this->assertTrue($result);

        // 清理环境变量
        unset($_ENV['SCAN_DETECT_404_FOUND_TIME']);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function maxAttemptsProvider(): array
    {
        return [
            'string number' => ['10', 10],
            'large number' => ['50', 50],
            'small number' => ['3', 3],
        ];
    }

    public function testServiceCanBeInstantiated(): void
    {
        $service = new ScanDetectService($this->cache);
        $this->assertInstanceOf(ScanDetectService::class, $service);
    }

    /**
     * 创建带有指定客户端IP的请求对象
     */
    private function createRequestWithIP(?string $ip): Request
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
