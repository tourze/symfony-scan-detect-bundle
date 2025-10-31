<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\Service;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * 扫描检测服务 - 基于Cache的实现
 *
 * 通过检测生成过多404错误的IP并进行阻断，使用PSR-16 SimpleCache
 * 提供针对恶意扫描和暴力攻击的保护。
 */
class ScanDetectService
{
    private const BLOCK_PREFIX = 'scan_detect_blocked_';
    private const COUNT_PREFIX = 'scan_detect_count_';
    private const DEFAULT_MAX_ATTEMPTS = 20;
    private const BLOCK_DURATION_SECONDS = 300; // 5 minutes
    private const COUNT_WINDOW_SECONDS = 60; // 1 minute

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * 检查IP地址是否当前被阻断
     */
    public function isIPBlocked(string $ipAddress): bool
    {
        if ($this->isSafeIP($ipAddress)) {
            return false;
        }

        $key = self::BLOCK_PREFIX . $ipAddress;

        return $this->cache->has($key);
    }

    /**
     * 记录IP的扫描尝试（404错误）
     */
    public function recordScanAttempt(Request $request, int $statusCode): void
    {
        $ipAddress = $request->getClientIp();
        if ($this->isSafeIP($ipAddress)) {
            return;
        }

        // Skip if IP is null
        if (null === $ipAddress) {
            return;
        }

        // Increment error count for this IP
        $key = self::COUNT_PREFIX . $ipAddress;
        $currentCount = (int) $this->cache->get($key, 0);
        $newCount = $currentCount + 1;

        // Store count with TTL of 1 minute
        $this->cache->set($key, $newCount, self::COUNT_WINDOW_SECONDS);
    }

    /**
     * 检查错误计数，如果超过阈值则阻断IP
     */
    public function checkAndBlockIP(string $ipAddress): bool
    {
        if ($this->isSafeIP($ipAddress)) {
            return false;
        }

        $maxAttempts = $this->getMaxAttempts();
        $key = self::COUNT_PREFIX . $ipAddress;
        $errorCount = (int) $this->cache->get($key, 0);

        if ($errorCount > $maxAttempts) {
            // Block the IP for 5 minutes
            $blockKey = self::BLOCK_PREFIX . $ipAddress;
            $this->cache->set($blockKey, time(), self::BLOCK_DURATION_SECONDS);

            return true;
        }

        return false;
    }

    /**
     * 手动解除IP地址的阻断
     */
    public function unblockIP(string $ipAddress): bool
    {
        $key = self::BLOCK_PREFIX . $ipAddress;

        return $this->cache->delete($key);
    }

    /**
     * 获取IP的当前错误计数
     */
    public function getErrorCount(string $ipAddress): int
    {
        if ($this->isSafeIP($ipAddress)) {
            return 0;
        }

        $key = self::COUNT_PREFIX . $ipAddress;

        return (int) $this->cache->get($key, 0);
    }

    /**
     * 重置IP的错误计数
     */
    public function resetErrorCount(string $ipAddress): bool
    {
        $key = self::COUNT_PREFIX . $ipAddress;

        return $this->cache->delete($key);
    }

    /**
     * 遗留清理方法 - 现在基本无操作，因为缓存自动处理TTL
     */
    public function cleanupExpiredBlocks(): int
    {
        // Cache automatically handles expiration, return 0 for compatibility
        return 0;
    }

    /**
     * 遗留清理方法 - 现在无操作，因为不再持久化记录
     */
    public function cleanupOldRecords(int $days = 30): int
    {
        // No persistent records to clean up, return 0 for compatibility
        return 0;
    }

    /**
     * 检查IP是否在安全列表中（不应被阻断）
     */
    private function isSafeIP(?string $ip): bool
    {
        return in_array($ip, [
            '127.0.0.1',
            '::1',
        ], true);
    }

    /**
     * 从环境变量获取最大尝试次数
     */
    private function getMaxAttempts(): int
    {
        $envValue = $_ENV['SCAN_DETECT_404_FOUND_TIME'] ?? $_SERVER['SCAN_DETECT_404_FOUND_TIME'] ?? null;

        if (null !== $envValue && is_numeric($envValue)) {
            return (int) $envValue;
        }

        return self::DEFAULT_MAX_ATTEMPTS;
    }
}
