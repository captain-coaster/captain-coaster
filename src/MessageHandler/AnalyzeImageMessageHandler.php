<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeImageMessage;
use App\Repository\ImageRepository;
use App\Service\Moderation\ImageModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AnalyzeImageMessageHandler
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly ImageModerationService $imageModerationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AnalyzeImageMessage $message): void
    {
        $image = $this->imageRepository->find($message->imageId);

        // The image may have been deleted (e.g. by an admin) between dispatch and processing.
        if (null === $image) {
            return;
        }

        // A failed fetch or call throws: Messenger retries, and once retries are exhausted,
        // AnalyzeImageFailureListener sends the photo to the moderation queue.
        $result = $this->imageModerationService->analyze($image);

        $this->imageModerationService->applyResult($image, $result);
        $this->entityManager->flush();
    }
}
