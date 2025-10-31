<?php

declare(strict_types=1);

namespace Tourze\ScanDetectBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\ScanDetectBundle\Command\ScanDetectCleanupCommand;
use Tourze\ScanDetectBundle\Service\ScanDetectService;

/**
 * ScanDetectCleanupCommand 单元测试
 *
 * 测试Cache-based扫描检测清理命令的功能
 *
 * @internal
 */
#[CoversClass(ScanDetectCleanupCommand::class)]
#[RunTestsInSeparateProcesses]
final class ScanDetectCleanupCommandTest extends AbstractCommandTestCase
{
    private ScanDetectService $scanDetectService;

    private ScanDetectCleanupCommand $command;

    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $this->scanDetectService = $this->createMock(ScanDetectService::class);
        // 将Mock服务注入到容器中，以便getService能够返回Mock对象
        self::getContainer()->set(ScanDetectService::class, $this->scanDetectService);

        $this->command = self::getService(ScanDetectCleanupCommand::class);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testCommandNameIsCorrect(): void
    {
        $this->assertSame('scan-detect:cleanup', $this->command->getName());
    }

    public function testCommandDescriptionIsCorrect(): void
    {
        $this->assertSame('清理扫描检测缓存（Cache-based架构下缓存自动过期）', $this->command->getDescription());
    }

    public function testCommandHasNoOptionsInCacheBasedArchitecture(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertFalse($definition->hasOption('days'));
    }

    public function testExecuteShowsCacheBasedArchitectureInfo(): void
    {
        // Cache-based架构下，清理方法返回0（因为缓存自动过期）
        $this->scanDetectService
            ->expects($this->once())
            ->method('cleanupExpiredBlocks')
            ->willReturn(0)
        ;

        $this->scanDetectService
            ->expects($this->once())
            ->method('cleanupOldRecords')
            ->willReturn(0)
        ;

        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('扫描检测清理工具 (Cache-based)', $output);
        $this->assertStringContainsString('IP阻断状态：自动5分钟过期', $output);
        $this->assertStringContainsString('错误计数：自动1分钟过期', $output);
        $this->assertStringContainsString('无需持久化存储，无旧数据积累', $output);
        $this->assertStringContainsString('Cache-based架构：所有数据自动过期，无需手动清理', $output);
        $this->assertStringContainsString('已从数据库存储迁移到内存缓存', $output);
        $this->assertStringContainsString('自动过期机制，无数据积累', $output);
        $this->assertStringContainsString('零维护成本，高性能防护', $output);
    }

    public function testCommandCanBeInstantiated(): void
    {
        $command = self::getService(ScanDetectCleanupCommand::class);
        $this->assertInstanceOf(ScanDetectCleanupCommand::class, $command);
    }

    public function testCommandIsCommand(): void
    {
        $this->assertInstanceOf(Command::class, $this->command);
    }
}
