<?php

namespace Tourze\ScanDetectBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tourze\ScanDetectBundle\ScanDetectBundle;

class ScanDetectBundleTest extends TestCase
{
    public function testInstantiation(): void
    {
        $bundle = new ScanDetectBundle();
        $this->assertInstanceOf(ScanDetectBundle::class, $bundle);
    }
}