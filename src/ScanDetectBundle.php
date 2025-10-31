<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class ScanDetectBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        // Cache-based架构无需Doctrine依赖
        return [];
    }
}
