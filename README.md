# Symfony Scan Detect Bundle

[![PHP Version Require](https://poser.pugx.org/tourze/symfony-scan-detect-bundle/require/php)](https://packagist.org/packages/tourze/symfony-scan-detect-bundle)
[![License](https://poser.pugx.org/tourze/symfony-scan-detect-bundle/license)](https://packagist.org/packages/tourze/symfony-scan-detect-bundle)
[![Build Status](https://github.com/tourze/symfony-scan-detect-bundle/workflows/CI/badge.svg)](https://github.com/tourze/symfony-scan-detect-bundle/actions)
[![Coverage Status](https://coveralls.io/repos/github/tourze/symfony-scan-detect-bundle/badge.svg?branch=master)](https://coveralls.io/github/tourze/symfony-scan-detect-bundle?branch=master)

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle that provides protection against malicious scanning and brute force attacks by detecting and blocking IPs that generate excessive 404 errors.

## Features

- **Automated IP blocking**: Automatically blocks IPs that generate excessive 404 errors
- **Configurable thresholds**: Set custom limits for error count and block duration
- **Safe IP whitelist**: Protects local IPs (127.0.0.1, ::1) from being blocked
- **Cache-based storage**: Uses PSR-16 SimpleCache for efficient tracking
- **Event-driven architecture**: Integrates seamlessly with Symfony's event system

## Installation

```bash
composer require tourze/symfony-scan-detect-bundle
```

## Quick Start

1. Add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\ScanDetectBundle\ScanDetectBundle::class => ['all' => true],
];
```

2. Configure the bundle by setting environment variables:

```bash
# Maximum number of 404 errors allowed within 1 minute (default: 20)
SCAN_DETECT_404_FOUND_TIME=20
```

3. The bundle will automatically start protecting your application from scanning attacks.

## Configuration

The bundle uses environment variables for configuration:

- `SCAN_DETECT_404_FOUND_TIME`: Maximum number of 404 errors allowed per IP within 1 minute (default: 20)

## How it works

1. **Request Monitoring**: The bundle monitors all incoming requests
2. **404 Error Tracking**: When a 404 error occurs, it's recorded for the client IP
3. **Threshold Detection**: If an IP exceeds the configured error threshold within 1 minute, it's marked as suspicious
4. **Automatic Blocking**: Suspicious IPs are blocked for 5 minutes with a 403 response
5. **Safe IP Protection**: Local IPs (127.0.0.1, ::1) are never blocked

## Example Usage

```php
// The bundle works automatically once installed
// No manual configuration required for basic usage

// For custom cache implementation:
use Psr\SimpleCache\CacheInterface;
use Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber;

// The subscriber is automatically registered via services.yaml
$cache = $container->get(CacheInterface::class);
$subscriber = new ScanDetect404Subscriber($cache);
```

## Console Commands

### scan-detect:cleanup

Provides cache management functionality for scan detection. In the Cache-based architecture, blocking and counting data automatically expire (blocking for 5 minutes, counting for 1 minute), so manual cleanup is usually not required.

```bash
# Run the cleanup command
php bin/console scan-detect:cleanup
```

**Command Features:**
- Shows current cache architecture status
- Provides manual cache cleanup options (though usually unnecessary)
- Displays optimization benefits of the cache-based approach

**Example Output:**
```
扫描检测清理工具 (Cache-based)
================================

缓存架构说明
-----------
• IP阻断状态：自动5分钟过期
• 错误计数：自动1分钟过期
• 无需持久化存储，无旧数据积累

✅ Cache-based架构：所有数据自动过期，无需手动清理

架构优化成果
-----------
✅ 已从数据库存储迁移到内存缓存
✅ 自动过期机制，无数据积累
✅ 零维护成本，高性能防护
```

## Testing

Run the test suite:

```bash
vendor/bin/phpunit packages/symfony-scan-detect-bundle/tests
```

## License

This bundle is released under the MIT license. See the [LICENSE](LICENSE) file for details.