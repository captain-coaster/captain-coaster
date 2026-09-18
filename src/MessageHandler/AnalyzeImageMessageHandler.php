<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeImageMessage;
use App\Repository\ImageRepository;
use App\Service\ImageModerationService;
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

        $result = $this->imageModerationService->analyze($image);

        // Bedrock error or unparseable response -- Messenger's retry strategy will retry this
        // message; if retries are exhausted, AnalyzeImageFailureListener creates a report so
        // it still surfaces in the review queue instead of silently vanishing.
        if (null === $result) {
            throw new \RuntimeException(\sprintf('Image moderation analysis failed for image #%d.', $image->getId()));
        }

        $this->imageModerationService->applyResult($image, $result);
        $this->entityManager->flush();
    }
}
