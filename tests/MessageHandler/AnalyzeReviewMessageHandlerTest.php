<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\RiddenCoaster;
use App\Message\AnalyzeReviewMessage;
use App\MessageHandler\AnalyzeReviewMessageHandler;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiTask;
use App\Service\Moderation\ReviewModerationService;
use App\Service\Moderation\ReviewRule;
use App\Service\Moderation\ReviewVerdict;
use App\Tests\AiStub;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AnalyzeReviewMessageHandlerTest extends TestCase
{
    private function handler(?RiddenCoaster $review, ReviewModerationService $moderationService, ?EntityManagerInterface $entityManager = null): AnalyzeReviewMessageHandler
    {
        $repository = $this->createMock(RiddenCoasterRepository::class);
        $repository->method('find')->with(1)->willReturn($review);

        return new AnalyzeReviewMessageHandler($repository, $moderationService, $entityManager ?? $this->createMock(EntityManagerInterface::class));
    }

    public function testIsANoOpWhenTheReviewIsGone(): void
    {
        $moderationService = $this->createMock(ReviewModerationService::class);
        $moderationService->expects($this->never())->method('analyze');

        $this->handler(null, $moderationService)(new AnalyzeReviewMessage(1));
    }

    public function testIsANoOpWhenTheReviewWasAlreadyAnalyzed(): void
    {
        $review = EntityId::set(new RiddenCoaster()->setReview('text')->setAnalyzedAt(new \DateTime()), 1);
        $moderationService = $this->createMock(ReviewModerationService::class);
        $moderationService->expects($this->never())->method('analyze');

        $this->handler($review, $moderationService)(new AnalyzeReviewMessage(1));
    }

    public function testAppliesTheVerdictAndFlushesOnSuccess(): void
    {
        $review = EntityId::set(new RiddenCoaster()->setReview('text'), 1);
        $verdict = new ReviewVerdict('en', ReviewRule::None, null);

        $moderationService = $this->createMock(ReviewModerationService::class);
        $moderationService->method('analyze')->with($review)->willReturn($verdict);
        $moderationService->expects($this->once())->method('applyVerdict')->with($review, $verdict);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $this->handler($review, $moderationService, $entityManager)(new AnalyzeReviewMessage(1));
    }

    public function testThrowsSoMessengerRetriesWhenAnalysisFails(): void
    {
        $review = EntityId::set(new RiddenCoaster()->setReview('text'), 1);
        $moderationService = $this->createMock(ReviewModerationService::class);
        $moderationService->method('analyze')->willThrowException(AiStub::failure(AiTask::ReviewModeration));
        $moderationService->expects($this->never())->method('applyVerdict');

        $this->expectException(\RuntimeException::class);
        $this->handler($review, $moderationService)(new AnalyzeReviewMessage(1));
    }
}
