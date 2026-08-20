<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CompareSummaryModelsCommand;
use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Service\CoasterSummaryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for CompareSummaryModelsCommand - never persists, only previews.
 */
class CompareSummaryModelsCommandTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private CoasterSummaryRepository&MockObject $coasterSummaryRepository;
    private CoasterSummaryService&MockObject $summaryService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->coasterSummaryRepository = $this->createMock(CoasterSummaryRepository::class);
        $this->summaryService = $this->createMock(CoasterSummaryService::class);

        $command = new CompareSummaryModelsCommand(
            $this->coasterRepository,
            $this->coasterSummaryRepository,
            $this->summaryService
        );

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testCoasterNotFoundReturnsFailure(): void
    {
        $this->coasterRepository->method('find')->willReturn(null);

        $this->commandTester->execute(['coaster-id' => '999']);

        $this->assertStringContainsString('Coaster not found', $this->commandTester->getDisplay());
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testNeverCallsGenerateSummary(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn(null);

        $this->summaryService->expects($this->never())->method('generateSummary');
        $this->summaryService->method('previewSummary')->willReturn([
            'summary' => 'A preview summary.',
            'pros' => ['fast'],
            'cons' => ['rough'],
            'metadata' => ['cost_usd' => 0.0005],
            'review_count' => 50,
            'total_review_count' => 50,
            'model_key' => 'gpt-5.6-luna',
        ]);

        $this->commandTester->execute(['coaster-id' => '1']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testNoStoredSummaryShowsNote(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn(null);
        $this->summaryService->method('previewSummary')->willReturn([
            'summary' => 'A preview summary.', 'pros' => [], 'cons' => [], 'metadata' => [],
            'review_count' => 50, 'total_review_count' => 50, 'model_key' => 'gpt-5.6-luna',
        ]);

        $this->commandTester->execute(['coaster-id' => '1']);

        $this->assertStringContainsString('No stored summary', $this->commandTester->getDisplay());
    }

    public function testStoredSummaryIsDisplayed(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);

        $storedSummary = new CoasterSummary();
        $storedSummary->setCoaster($coaster);
        $storedSummary->setSummary('Stored summary text.');
        $storedSummary->setPositiveVotes(5);
        $storedSummary->setNegativeVotes(1);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn($storedSummary);

        $this->summaryService->method('previewSummary')->willReturn([
            'summary' => 'A preview summary.', 'pros' => [], 'cons' => [], 'metadata' => [],
            'review_count' => 50, 'total_review_count' => 50, 'model_key' => 'gpt-5.6-luna',
        ]);

        $this->commandTester->execute(['coaster-id' => '1']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Stored summary text.', $output);
        $this->assertStringContainsString('👍 5', $output);
        $this->assertStringContainsString('👎 1', $output);
    }

    public function testInsufficientReviewsShowsWarningInsteadOfSummary(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn(null);

        $this->summaryService->method('previewSummary')->willReturn([
            'summary' => null, 'pros' => [], 'cons' => [], 'metadata' => null,
            'reason' => 'insufficient_reviews', 'review_count' => 5,
        ]);

        $this->commandTester->execute(['coaster-id' => '1']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No preview generated', $output);
        $this->assertStringContainsString('insufficient_reviews', $output);
    }

    public function testBackfillIsShownInReviewsLabel(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn(null);

        $this->summaryService->method('previewSummary')->willReturn([
            'summary' => 'A preview summary.', 'pros' => [], 'cons' => [], 'metadata' => [],
            'review_count' => 30, 'total_review_count' => 100, 'model_key' => 'gpt-5.6-luna',
        ]);

        $this->commandTester->execute(['coaster-id' => '1', '--language' => 'fr']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('30 same-language + 70 backfilled = 100 total', $output);
    }

    public function testNoVocabGuideOptionIsPassedThrough(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn(null);

        $this->summaryService
            ->expects($this->exactly(2))
            ->method('previewSummary')
            ->with($coaster, $this->anything(), 'fr', false)
            ->willReturn([
                'summary' => 'A preview summary.', 'pros' => [], 'cons' => [], 'metadata' => [],
                'review_count' => 50, 'total_review_count' => 50, 'model_key' => 'gpt-5.6-luna',
            ]);

        $this->commandTester->execute(['coaster-id' => '1', '--language' => 'fr', '--no-vocab-guide' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
