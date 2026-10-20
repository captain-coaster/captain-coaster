<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ReviewReport;
use App\Message\AnalyzeReviewMessage;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Once AnalyzeReviewMessage's retries are exhausted, the review goes to the moderation queue,
 * as AnalyzeImageFailureListener does for photos: it stays hidden until a moderator decides.
 */
#[AsEventListener]
final class AnalyzeReviewFailureListener
{
    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
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

        // Analyzed, so the recovery run leaves it to the moderator.
        $review->setAnalyzedAt(new \DateTime());
        $this->entityManager->persist(ReviewReport::fromAi(
            $review,
            ReviewReport::REASON_ANALYSIS_FAILED,
            null,
            $event->getThrowable()->getMessage(),
        ));
        $this->entityManager->flush();
    }
}
