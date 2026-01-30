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
 * Unit tests for GenerateCoasterSummariesCommand simplified interface.
 * Updated for task 7: simplified command interface without translation-related options.
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
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testForceBadReviewsOption(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->summaryRepository
            ->expects($this->once())
            ->method('findCoastersWithBadReviews')
            ->with(5, null)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--force-bad-reviews' => 5,
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Force regeneration mode', $output);
        $this->assertStringContainsString('5+', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testDefaultLanguageIsEnglish(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->with(20, null)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Target languages: en', $output);
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
        $this->assertStringContainsString('Target languages: fr, es', $output);
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

        $this->commandTester->execute([
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Dry run', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}