<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\ScanDetectBundle\Service\ScanDetectService;

#[AsCommand(
    name: 'scan-detect:cleanup',
    description: '清理扫描检测缓存（Cache-based架构下缓存自动过期）'
)]
class ScanDetectCleanupCommand extends Command
{
    public function __construct(
        private readonly ScanDetectService $scanDetectService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            '此命令提供扫描检测的缓存管理功能。在Cache-based架构下，'
            . '阻断和计数数据会自动过期（阻断5分钟，计数1分钟），'
            . '通常不需要手动清理。'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('扫描检测清理工具 (Cache-based)');

        $io->section('缓存架构说明');
        $io->info([
            '• IP阻断状态：自动5分钟过期',
            '• 错误计数：自动1分钟过期',
            '• 无需持久化存储，无旧数据积累',
        ]);

        // 提供手动清理选项（虽然通常不需要）
        $io->section('手动缓存管理');

        // 清理功能（在新架构下返回0，因为缓存自动过期）
        $expiredCount = $this->scanDetectService->cleanupExpiredBlocks();
        $oldRecordsCount = $this->scanDetectService->cleanupOldRecords();

        if (0 === $expiredCount && 0 === $oldRecordsCount) {
            $io->success('Cache-based架构：所有数据自动过期，无需手动清理');
        }

        $io->section('架构优化成果');
        $io->success([
            '✅ 已从数据库存储迁移到内存缓存',
            '✅ 自动过期机制，无数据积累',
            '✅ 零维护成本，高性能防护',
        ]);

        return Command::SUCCESS;
    }
}
