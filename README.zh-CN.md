# Symfony Scan Detect Bundle

[![PHP Version Require](https://poser.pugx.org/tourze/symfony-scan-detect-bundle/require/php)](https://packagist.org/packages/tourze/symfony-scan-detect-bundle)
[![License](https://poser.pugx.org/tourze/symfony-scan-detect-bundle/license)](https://packagist.org/packages/tourze/symfony-scan-detect-bundle)
[![Build Status](https://github.com/tourze/symfony-scan-detect-bundle/workflows/CI/badge.svg)](https://github.com/tourze/symfony-scan-detect-bundle/actions)
[![Coverage Status](https://coveralls.io/repos/github/tourze/symfony-scan-detect-bundle/badge.svg?branch=master)](https://coveralls.io/github/tourze/symfony-scan-detect-bundle?branch=master)

[English](README.md) | [中文](README.zh-CN.md)

一个 Symfony Bundle，通过检测和阻止产生过多 404 错误的 IP 地址来保护应用程序免受恶意扫描和暴力攻击。

## 功能特性

- **自动 IP 阻止**: 自动阻止产生过多 404 错误的 IP 地址
- **可配置阈值**: 设置自定义错误计数和阻止持续时间限制
- **安全 IP 白名单**: 保护本地 IP（127.0.0.1, ::1）不被阻止
- **基于缓存的存储**: 使用 PSR-16 SimpleCache 进行高效跟踪
- **事件驱动架构**: 与 Symfony 的事件系统无缝集成

## 安装

```bash
composer require tourze/symfony-scan-detect-bundle
```

## 快速开始

1. 将 Bundle 添加到您的 `config/bundles.php`：

```php
return [
    // ...
    Tourze\ScanDetectBundle\ScanDetectBundle::class => ['all' => true],
];
```

2. 通过设置环境变量配置 Bundle：

```bash
# 1分钟内允许的最大 404 错误次数（默认：20）
SCAN_DETECT_404_FOUND_TIME=20
```

3. Bundle 将自动开始保护您的应用程序免受扫描攻击。

## 配置

Bundle 使用环境变量进行配置：

- `SCAN_DETECT_404_FOUND_TIME`: 1分钟内每个 IP 允许的最大 404 错误次数（默认：20）

## 工作原理

1. **请求监控**: Bundle 监控所有传入请求
2. **404 错误跟踪**: 当发生 404 错误时，记录客户端 IP
3. **阈值检测**: 如果 IP 在 1 分钟内超过配置的错误阈值，则标记为可疑
4. **自动阻止**: 可疑 IP 被阻止 5 分钟，返回 403 响应
5. **安全 IP 保护**: 本地 IP（127.0.0.1, ::1）永远不会被阻止

## 使用示例

```php
// Bundle 安装后自动工作
// 基本使用无需手动配置

// 自定义缓存实现：
use Psr\SimpleCache\CacheInterface;
use Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber;

// 订阅者通过 services.yaml 自动注册
$cache = $container->get(CacheInterface::class);
$subscriber = new ScanDetect404Subscriber($cache);
```

## 控制台命令

### scan-detect:cleanup

提供扫描检测的缓存管理功能。在Cache-based架构下，阻断和计数数据会自动过期（阻断5分钟，计数1分钟），通常不需要手动清理。

```bash
# 运行清理命令
php bin/console scan-detect:cleanup
```

**命令特性：**
- 显示当前缓存架构状态
- 提供手动缓存清理选项（虽然通常不需要）
- 展示缓存架构的优化效果

**命令输出示例：**
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

## 测试

运行测试套件：

```bash
vendor/bin/phpunit packages/symfony-scan-detect-bundle/tests
```

## 许可证

此 Bundle 基于 MIT 许可证发布。详情请参阅 [LICENSE](LICENSE) 文件。
