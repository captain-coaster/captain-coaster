<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\GenerateCoasterSummariesCommand;
use App\Entity\Coaster;
use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Service\CoasterSummaryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for GenerateCoasterSummariesCommand option validation and behavior.
 */
class GenerateCoasterSummariesCommandTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private CoasterSummaryRepository&MockObject $summaryRepository;
    private CoasterSummaryService&MockObject $summaryService;
    private LoggerInterface&MockObject $logger;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->summaryRepository = $this->createMock(CoasterSummaryRepository::class);
        $this->summaryService = $this->createMock(CoasterSummaryService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $command = new GenerateCoasterSummariesCommand(
            $this->coasterRepository,
            $this->summaryRepository,
            $this->summaryService,
            $this->logger
        );

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testRejectsConflictingNoTranslateAndTranslateOnly(): void
    {
        $this->commandTester->execute([
            '--no-translate' => true,
            '--translate-only' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Cannot use --no-translate and --translate-only together', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testTranslateOnlyLoadsCoastersWithSummaries(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->summaryRepository
            ->expects($this->once())
            ->method('findCoastersWithSummaries')
            ->with('en', 10)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--translate-only' => true,
            '--limit' => 10,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testNormalModeLoadsEligibleCoasters(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->with(20, 5)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--limit' => 5,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testSingleCoasterById(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('find')
            ->with('123')
            ->willReturn($coaster);

        $this->commandTester->execute([
            '--coaster-id' => '123',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testInvalidCoasterIdReturnsError(): void
    {
        $this->coasterRepository
            ->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null);

        $this->commandTester->execute([
            '--coaster-id' => '999',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Coaster with ID 999 not found', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testLanguagesOptionParsesCorrectly(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--languages' => 'fr,es',
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Translation enabled for languages: fr, es', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testDryRunDoesNotCallService(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->willReturn([$coaster]);

        $this->summaryService
            ->expects($this->never())
            ->method('generateSummary');

        $this->summaryService
            ->expects($this->never())
            ->method('translateSummary');

        $this->commandTester->execute([
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dry run', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
