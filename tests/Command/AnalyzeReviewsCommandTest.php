<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\AnalyzeReviewsCommand;
use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use App\Service\ReviewModerationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeReviewsCommandTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private ReviewModerationService&MockObject $moderationService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->moderationService = $this->createMock(ReviewModerationService::class);

        $command = new AnalyzeReviewsCommand($this->riddenCoasterRepository, $this->moderationService);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createPersistedReview(int $id, string $text): RiddenCoaster
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue(3.0);
        $review->setReview($text);

        $reflection = new \ReflectionProperty(RiddenCoaster::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($review, $id);

        return $review;
    }

    public function testSampleModeAnalyzesEachReturnedReview(): void
    {
        $review = $this->createPersistedReview(42, 'great ride');

        $this->riddenCoasterRepository->expects($this->once())
            ->method('findRandomReviewsWithText')
            ->with(5)
            ->willReturn([$review]);

        $this->moderationService->expects($this->once())
            ->method('analyze')
            ->with($review)
            ->willReturn(['language' => 'en', 'category' => 'ok', 'confidence' => 'high', 'explanation' => null]);

        $this->commandTester->execute(['--sample' => 5]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Review 42', $output);
        $this->assertStringContainsString('category=ok', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testTextModeAnalyzesAdHocReviewWithoutTouchingRepository(): void
    {
        $this->riddenCoasterRepository->expects($this->never())->method('findRandomReviewsWithText');

        $this->moderationService->expects($this->once())
            ->method('analyze')
            ->willReturn(['language' => 'en', 'category' => 'toxic', 'confidence' => 'high', 'explanation' => 'Pure insult.']);

        $this->commandTester->execute([
            '--text' => 'i fucking hate this coaster',
            '--rating' => '1',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Review ad-hoc', $output);
        $this->assertStringContainsString('category=toxic', $output);
        $this->assertStringContainsString('Pure insult.', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testFailedAnalysisIsReportedNotFatal(): void
    {
        $review = $this->createPersistedReview(7, 'a review');

        $this->riddenCoasterRepository->method('findRandomReviewsWithText')->willReturn([$review]);
        $this->moderationService->method('analyze')->willReturn(null);

        $this->commandTester->execute(['--sample' => 1]);

        $this->assertStringContainsString('analysis failed', $this->commandTester->getDisplay());
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testNoOptionsFailsWithHelpfulMessage(): void
    {
        $this->commandTester->execute([]);

        $this->assertStringContainsString('--sample', $this->commandTester->getDisplay());
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }
}
