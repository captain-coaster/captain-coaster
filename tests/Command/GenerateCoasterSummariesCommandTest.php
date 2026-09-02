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

    public function testMinDownvotesOption(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->summaryRepository
            ->expects($this->once())
            ->method('findCoastersWithBadReviews')
            ->with('en', 5, null)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--min-downvotes' => 5,
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Force regeneration mode', $output);
        $this->assertStringContainsString('5+', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testMinDownvotesZeroRegeneratesEveryExistingSummary(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->summaryRepository
            ->expects($this->once())
            ->method('findCoastersWithBadReviews')
            ->with('fr', 0, null)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--languages' => 'fr',
            '--min-downvotes' => 0,
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('0+ downvotes', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testMinDownvotesRequiresExactlyOneLanguage(): void
    {
        $this->summaryRepository
            ->expects($this->never())
            ->method('findCoastersWithBadReviews');

        $this->commandTester->execute([
            '--languages' => 'en,fr',
            '--min-downvotes' => 5,
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('requires exactly one --languages value', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testMinDownvotesCannotBeCombinedWithCoasterId(): void
    {
        $this->coasterRepository->expects($this->never())->method('find');
        $this->summaryRepository->expects($this->never())->method('findCoastersWithBadReviews');

        $this->commandTester->execute([
            '--coaster-id' => '123',
            '--min-downvotes' => 5,
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('cannot be', $output);
        $this->assertStringContainsString('combined', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testGeneratedWithModelOption(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->summaryRepository
            ->expects($this->once())
            ->method('findCoastersWithAiModel')
            ->with('en', 'gpt-oss-120b', null)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--generated-with-model' => 'gpt-oss-120b',
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Force regeneration mode', $output);
        $this->assertStringContainsString("'gpt-oss-120b'", $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testGeneratedWithModelRequiresExactlyOneLanguage(): void
    {
        $this->summaryRepository
            ->expects($this->never())
            ->method('findCoastersWithAiModel');

        $this->commandTester->execute([
            '--languages' => 'en,fr',
            '--generated-with-model' => 'gpt-oss-120b',
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('requires exactly one --languages value', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testGeneratedWithModelCannotBeCombinedWithCoasterId(): void
    {
        $this->coasterRepository->expects($this->never())->method('find');
        $this->summaryRepository->expects($this->never())->method('findCoastersWithAiModel');

        $this->commandTester->execute([
            '--coaster-id' => '123',
            '--generated-with-model' => 'gpt-oss-120b',
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('cannot be', $output);
        $this->assertStringContainsString('combined', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testGeneratedWithModelCannotBeCombinedWithMinDownvotes(): void
    {
        $this->summaryRepository->expects($this->never())->method('findCoastersWithBadReviews');
        $this->summaryRepository->expects($this->never())->method('findCoastersWithAiModel');

        $this->commandTester->execute([
            '--min-downvotes' => 5,
            '--generated-with-model' => 'gpt-oss-120b',
            '--dry-run' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('cannot be', $output);
        $this->assertStringContainsString('combined', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testDefaultLanguageIsEnglish(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->with(20, null, 'en')
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
            ->with(20, 5, 'en')
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

    public function testCoasterIdAlwaysRegeneratesRegardlessOfDueness(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository->method('find')->willReturn($coaster);

        // Not due for a regen, but --coaster-id was explicit - should still run.
        $this->summaryService->method('shouldUpdateSummary')->willReturn(false);
        $this->summaryService
            ->expects($this->once())
            ->method('generateSummary')
            ->willReturn(['summary' => null, 'metadata' => null, 'reason' => 'ai_error']);

        $this->commandTester->execute([
            '--coaster-id' => '123',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringNotContainsString('Skipping', $output);
    }

    public function testInsufficientReviewsDoesNotLogAtCommandLevel(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->summaryService->method('generateSummary')
            ->willReturn(['summary' => null, 'metadata' => null, 'reason' => 'insufficient_reviews', 'review_count' => 3]);

        $this->logger->expects($this->never())->method('error');
        $this->logger->expects($this->never())->method('info');

        $this->commandTester->execute(['--coaster-id' => '123']);
    }

    public function testAiErrorStillLogsAtErrorLevel(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->summaryService->method('generateSummary')
            ->willReturn(['summary' => null, 'metadata' => null, 'reason' => 'ai_error']);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to generate summary', $this->anything());

        $this->commandTester->execute(['--coaster-id' => '123']);
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

    public function testSingleLanguageScopesTheEligibilityCount(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->with(20, 50, 'fr')
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--languages' => 'fr',
            '--limit' => 50,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testMultipleLanguagesDoNotScopeTheEligibilityCount(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->coasterRepository
            ->expects($this->once())
            ->method('findEligibleForSummary')
            ->with(20, null, null)
            ->willReturn([$coaster]);

        $this->commandTester->execute([
            '--languages' => 'en,fr',
            '--dry-run' => true,
        ]);

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
