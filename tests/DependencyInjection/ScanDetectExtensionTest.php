<?php

namespace Tourze\ScanDetectBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\ScanDetectBundle\DependencyInjection\ScanDetectExtension;

/**
 * ScanDetectExtension 测试
 */
class ScanDetectExtensionTest extends TestCase
{
    private ScanDetectExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new ScanDetectExtension();
        $this->container = new ContainerBuilder();
    }

    /**
     * 测试扩展是否能正确加载服务配置
     */
    public function testLoad(): void
    {
        $configs = [];
        
        $this->extension->load($configs, $this->container);
        
        // 验证服务定义是否已加载
        // 检查自动配置的服务
        $this->assertTrue(
            $this->container->hasDefinition('Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber') ||
            $this->container->has('Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber'),
            'ScanDetect404Subscriber 服务未正确加载'
        );
    }

    /**
     * 测试扩展加载时使用空配置
     */
    public function testLoadWithEmptyConfig(): void
    {
        $this->extension->load([], $this->container);
        
        // 确保不抛出异常并正常加载
        $this->assertInstanceOf(ContainerBuilder::class, $this->container);
    }

    /**
     * 测试扩展加载时使用多个配置集
     */
    public function testLoadWithMultipleConfigs(): void
    {
        $configs = [
            [],
            [],
        ];
        
        $this->extension->load($configs, $this->container);
        
        // 确保不抛出异常并正常加载
        $this->assertInstanceOf(ContainerBuilder::class, $this->container);
    }
}