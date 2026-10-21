<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeReviewMessage;
use App\Repository\RiddenCoasterRepository;
use App\Service\Moderation\ReviewModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AnalyzeReviewMessageHandler
{
    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly ReviewModerationService $reviewModerationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AnalyzeReviewMessage $message): void
    {
        $review = $this->riddenCoasterRepository->find($message->reviewId);

        // Deleted, emptied, or already analyzed (the same text sent twice) since dispatch.
        if (null === $review || !$review->needsAnalysis()) {
            return;
        }

        // A failed call throws: Messenger retries, and once retries are exhausted,
        // AnalyzeReviewFailureListener sends the review to the moderation queue.
        $this->reviewModerationService->applyVerdict($review, $this->reviewModerationService->analyze($review));
        $this->entityManager->flush();
    }
}
