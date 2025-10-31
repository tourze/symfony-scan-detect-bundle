<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class ScanDetectExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
