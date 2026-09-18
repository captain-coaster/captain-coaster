<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ImageReport;
use App\Message\AnalyzeImageMessage;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * When AnalyzeImageMessage's retries are exhausted, create an ImageReport instead of letting
 * the image sit invisible forever with no signal anyone would see -- this surfaces it in the
 * same admin queue as moderation flags; approving it publishes via the existing entropy-crop
 * fallback, since no focal point was ever written. The message still also lands in the
 * `failed` Doctrine transport as usual, for ops-facing replay after fixing the underlying issue.
 */
#[AsEventListener]
final class AnalyzeImageFailureListener
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof AnalyzeImageMessage) {
            return;
        }

        $image = $this->imageRepository->find($message->imageId);
        if (null === $image) {
            return;
        }

        $report = new ImageReport()
            ->setImage($image)
            ->setImageFilename($image->getFilename())
            ->setCoasterName($image->getCoaster()->getName())
            ->setCategories([ImageReport::CATEGORY_ANALYSIS_FAILED])
            ->setAiExplanation($event->getThrowable()->getMessage());

        $this->entityManager->persist($report);
        $this->entityManager->flush();
    }
}
