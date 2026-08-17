<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\AnalyzeReviewsCommand;
use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\ReviewReportRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\ReviewModerationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeReviewsCommandTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private ReviewModerationService&MockObject $moderationService;
    private ReviewReportRepository&MockObject $reviewReportRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->moderationService = $this->createMock(ReviewModerationService::class);
        $this->reviewReportRepository = $this->createMock(ReviewReportRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $command = new AnalyzeReviewsCommand(
            $this->riddenCoasterRepository,
            $this->moderationService,
            $this->reviewReportRepository,
            $this->entityManager
        );

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

        $user = new User();
        $user->setDisplayName('Jane Doe');
        $userIdReflection = new \ReflectionProperty(User::class, 'id');
        $userIdReflection->setAccessible(true);
        $userIdReflection->setValue($user, 1000 + $id);
        $review->setUser($user);

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

    public function testDefaultModeQueriesPendingWithSinceWindow(): void
    {
        $this->riddenCoasterRepository->expects($this->once())
            ->method('findPendingAnalysis')
            ->with($this->isInstanceOf(\DateTimeInterface::class), 50)
            ->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testAllFlagPassesNullSince(): void
    {
        $this->riddenCoasterRepository->expects($this->once())
            ->method('findPendingAnalysis')
            ->with(null, 50)
            ->willReturn([]);

        $this->commandTester->execute(['--all' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testFlaggedReviewCreatesReportWhenNotDryRun(): void
    {
        $review = $this->createPersistedReview(99, 'i fucking hate this coaster');

        $this->riddenCoasterRepository->method('findPendingAnalysis')->willReturn([$review]);
        $this->moderationService->method('analyze')->willReturn([
            'language' => 'en',
            'category' => 'toxic',
            'confidence' => 'high',
            'explanation' => 'Pure insult, no substance.',
        ]);
        $this->reviewReportRepository->method('hasUnresolvedAiReport')->with($review)->willReturn(false);

        $persisted = [];
        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $this->entityManager->expects($this->atLeastOnce())->method('flush');

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());

        $reports = array_values(array_filter($persisted, static fn ($entity) => $entity instanceof ReviewReport));
        $this->assertCount(1, $reports);
        $this->assertSame('Jane Doe', $reports[0]->getReviewerName());
        $this->assertSame(1099, $reports[0]->getReviewerId());
    }

    /**
     * A flush failure aborts the whole command; no per-review isolation.
     */
    public function testFlushFailurePropagatesAndAbortsTheCommand(): void
    {
        $review = $this->createPersistedReview(201, 'a fine review');

        $this->riddenCoasterRepository->method('findPendingAnalysis')->willReturn([$review]);
        $this->moderationService->method('analyze')->willReturn([
            'language' => 'en',
            'category' => 'toxic',
            'confidence' => 'high',
            'explanation' => 'Pure insult, no substance.',
        ]);
        $this->reviewReportRepository->method('hasUnresolvedAiReport')->willReturn(false);
        $this->entityManager->method('flush')->willThrowException(new \RuntimeException('DB connectivity blip'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB connectivity blip');

        $this->commandTester->execute([]);
    }

    public function testDryRunDoesNotPersist(): void
    {
        $review = $this->createPersistedReview(100, 'i fucking hate this coaster');

        $this->riddenCoasterRepository->method('findPendingAnalysis')->willReturn([$review]);
        $this->moderationService->method('analyze')->willReturn([
            'language' => 'en',
            'category' => 'toxic',
            'confidence' => 'high',
            'explanation' => 'Pure insult, no substance.',
        ]);

        $this->entityManager->expects($this->never())->method('flush');

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testDuplicateAiReportIsSkipped(): void
    {
        $review = $this->createPersistedReview(101, 'i fucking hate this coaster');

        $this->riddenCoasterRepository->method('findPendingAnalysis')->willReturn([$review]);
        $this->moderationService->method('analyze')->willReturn([
            'language' => 'en',
            'category' => 'toxic',
            'confidence' => 'high',
            'explanation' => 'Pure insult, no substance.',
        ]);
        $this->reviewReportRepository->method('hasUnresolvedAiReport')->willReturn(true);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('already has a pending AI report', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
