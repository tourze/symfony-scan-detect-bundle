<?php

namespace Tourze\ScanDetectBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Tourze\ScanDetectBundle\ScanDetectBundle;

/**
 * 用于集成测试的测试内核
 */
class IntegrationTestKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ScanDetectBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        // 基本框架配置
        $container->extension('framework', [
            'secret' => 'TEST_SECRET',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'uid' => [
                'default_uuid_version' => 7,
                'time_based_uuid_version' => 7,
            ],
            'validation' => [
                'email_validation_mode' => 'html5',
            ],
            'php_errors' => [
                'log' => true,
            ],
        ]);

        // 添加测试服务
        $container->services()
            ->set('test.cache', \Symfony\Component\Cache\Psr16Cache::class)
            ->args([new \Symfony\Component\DependencyInjection\Reference('test.cache.adapter')])
            ->alias(\Psr\SimpleCache\CacheInterface::class, 'test.cache');

        $container->services()
            ->set('test.cache.adapter', \Symfony\Component\Cache\Adapter\ArrayAdapter::class);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }
}
