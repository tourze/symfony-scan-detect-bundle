<?php

namespace Tourze\ScanDetectBundle\Tests;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SymfonyTestingFramework\Kernel;
use Tourze\ScanDetectBundle\ScanDetectBundle;

/**
 * @internal
 * @coversNothing
 * @phpstan-ignore-next-line forbiddenExtendOfNonAbstractClass
 */
class TestKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct(
            'test',
            true,
            __DIR__ . '/../',
            [ScanDetectBundle::class => ['all' => true]]
        );
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // 手动注册我们的测试实体映射
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'ScanDetectBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => true,
                        'prefix' => 'Tourze\ScanDetectBundle\Entity',
                        'alias' => 'ScanDetectBundle',
                    ],
                ],
            ],
        ]);

        // 禁用可能引起问题的配置
        $container->prependExtensionConfig('framework', [
            'test' => true,
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ]);

        // 手动注册DataFixtures服务
        $container->autowire('tourze_scan_detect.data_fixtures.blocked_ip', 'Tourze\ScanDetectBundle\DataFixtures\BlockedIPFixtures')
            ->setPublic(true)
            ->addTag('doctrine.fixture.orm')
        ;

        $container->autowire('tourze_scan_detect.data_fixtures.scan_record', 'Tourze\ScanDetectBundle\DataFixtures\ScanRecordFixtures')
            ->setPublic(true)
            ->addTag('doctrine.fixture.orm')
        ;

        $container->autowire('tourze_scan_detect.data_fixtures.scan_rule', 'Tourze\ScanDetectBundle\DataFixtures\ScanRuleFixtures')
            ->setPublic(true)
            ->addTag('doctrine.fixture.orm')
        ;
    }
}
