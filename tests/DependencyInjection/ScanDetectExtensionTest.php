<?php

namespace Tourze\ScanDetectBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\ScanDetectBundle\DependencyInjection\ScanDetectExtension;

/**
 * @internal
 */
#[CoversClass(ScanDetectExtension::class)]
final class ScanDetectExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function setUp(): void
    {
        // 集成测试不需要额外的设置
    }

    protected function getContainer(): ContainerBuilder
    {
        // 创建一个真实的容器用于测试
        $container = new ContainerBuilder();

        // 设置必需的参数
        $container->setParameter('kernel.environment', 'test');

        // 注册缓存服务
        $container->register('cache.app', ArrayAdapter::class);
        $container->register('Psr\SimpleCache\CacheInterface', Psr16Cache::class)
            ->addArgument(new Reference('cache.app'))
        ;

        return $container;
    }

    /**
     * 测试扩展是否能正确加载服务配置
     */
    public function testLoad(): void
    {
        $configs = [];
        $extension = new ScanDetectExtension();
        $container = $this->getContainer();
        $extension->load($configs, $container);

        // 验证服务定义是否已加载
        // 检查自动配置的服务
        $this->assertTrue(
            $container->hasDefinition('Tourze\ScanDetectBundle\EventSubscriber\ScanDetect404Subscriber'),
            'ScanDetect404Subscriber 服务未正确加载'
        );
    }

    /**
     * 测试扩展加载时使用空配置
     */
    public function testLoadWithEmptyConfig(): void
    {
        $extension = new ScanDetectExtension();
        $container = $this->getContainer();
        $extension->load([], $container);

        // 确保不抛出异常并正常加载
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ContainerBuilder', $container);
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

        $extension = new ScanDetectExtension();
        $container = $this->getContainer();
        $extension->load($configs, $container);

        // 确保不抛出异常并正常加载
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ContainerBuilder', $container);
    }
}
