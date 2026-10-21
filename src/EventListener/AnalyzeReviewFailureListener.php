<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ReviewReport;
use App\Message\AnalyzeReviewMessage;
use App\Repository\ReviewReportRepository;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Once AnalyzeReviewMessage's retries are exhausted, the review is treated as a gray zone:
 * published, and sent to the moderation queue. Unlike photos, a review in doubt is shown.
 */
#[AsEventListener]
final class AnalyzeReviewFailureListener
{
    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly ReviewReportRepository $reviewReportRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof AnalyzeReviewMessage) {
            return;
        }

        $review = $this->riddenCoasterRepository->find($message->reviewId);
        if (null === $review || !$review->needsAnalysis()) {
            return;
        }

        // Analyzed, so the recovery run leaves it to the moderator. A member report keeps it hidden.
        $review->setAnalyzedAt(new \DateTime());
        if (!$this->reviewReportRepository->hasUnresolvedReport($review)) {
            $review->setReviewEnabled(true);
        }
        $this->entityManager->persist(ReviewReport::fromAi($review, ReviewReport::REASON_ANALYSIS_FAILED, $event->getThrowable()->getMessage()));
        $this->entityManager->flush();
    }
}
