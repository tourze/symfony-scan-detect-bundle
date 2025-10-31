<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\ScanDetectBundle\ScanDetectBundle;

/**
 * @internal
 */
#[CoversClass(ScanDetectBundle::class)]
#[RunTestsInSeparateProcesses]
final class ScanDetectBundleTest extends AbstractBundleTestCase
{
}
