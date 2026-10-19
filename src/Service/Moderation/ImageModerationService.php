<?php

declare(strict_types=1);

namespace App\Service\Moderation;

use App\Entity\Image;
use App\Entity\ImageReport;
use App\Repository\ImageReportRepository;
use App\Service\Ai\AiTask;
use App\Service\Ai\BedrockService;
use App\Service\ImageManager;
use App\Service\PictureUrlSigner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GenAI photo moderation + focal-point detection, one Bedrock vision call per image.
 *
 * analyze() mirrors ReviewModerationService's shape (pure analysis, no persistence).
 * applyResult() is new relative to that pattern -- it exists because, unlike review
 * moderation (one caller, AnalyzeReviewsCommand), this has two callers needing identical
 * persistence/orchestration logic (AnalyzeImageMessageHandler and ReprocessImagesCommand),
 * so factoring it out here avoids duplicating it in both.
 */
class ImageModerationService
{
    /** Strict mode: the answer always matches it, so it is the only validation. */
    private const OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'categories' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => [
                ImageReport::CATEGORY_OFFTOPIC,
                ImageReport::CATEGORY_RETOUCHED,
                ImageReport::CATEGORY_PEOPLE_SUBJECT,
                ImageReport::CATEGORY_ONRIDE_PHOTO,
            ]]],
            'focal_x' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'focal_y' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
            'explanation' => ['type' => ['string', 'null']],
        ],
        'required' => ['categories', 'focal_x', 'focal_y', 'confidence', 'explanation'],
        'additionalProperties' => false,
    ];

    public function __construct(
        private readonly BedrockService $bedrockService,
        private readonly HttpClientInterface $httpClient,
        private readonly PictureUrlSigner $pictureUrlSigner,
        private readonly ImageManager $imageManager,
        private readonly ImageReportRepository $imageReportRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $moderationLogger,
    ) {
    }

    /** @return array{categories: string[], focalX: float, focalY: float, confidence: string, explanation: ?string}|null */
    public function analyze(Image $image): ?array
    {
        $imageId = $this->imageIdForLogging($image);

        try {
            // The 1440x1440 "lightbox" derivative is already generated/cached by the existing
            // pipeline for other consumers (User/images.html.twig, Coaster/_image_panel.html.twig)
            // and is letterboxed (INSIDE_FIT_SIZES in captain-aws), not cropped -- the whole
            // photo, just downscaled, which is what moderation/focal-point detection needs. This
            // avoids adding any PHP-side image manipulation just to bound the Bedrock payload size.
            // jpg, not avif: Bedrock's Converse API ImageFormat only accepts png/jpeg/gif/webp.
            $url = $this->pictureUrlSigner->signImage($image, 1440, 1440, 'jpg');
            $imageBytes = $this->httpClient->request('GET', $url)->getContent();
        } catch (\Throwable $e) {
            $this->moderationLogger->error('Image moderation could not fetch the lightbox derivative', [
                'image_id' => $imageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $response = $this->bedrockService->invoke(
            AiTask::ImageModeration,
            $this->buildPrompt(),
            'Moderate this photo.',
            self::OUTPUT_SCHEMA,
            subjectId: \is_int($imageId) ? $imageId : null,
            jpegBytes: $imageBytes,
        );

        if (!$response['success']) {
            $this->moderationLogger->error('Image moderation Bedrock call failed', [
                'image_id' => $imageId,
                'error' => $response['error'],
                'error_code' => $response['error_code'],
            ]);

            return null;
        }

        $data = $response['data'];
        $result = $this->flagUncertainVerdict([
            'categories' => array_map(strval(...), (array) $data['categories']),
            'focalX' => (float) $data['focal_x'],
            'focalY' => (float) $data['focal_y'],
            'confidence' => (string) $data['confidence'],
            'explanation' => \is_string($data['explanation']) ? $data['explanation'] : null,
        ]);

        // Logged unconditionally (not just on flag) -- a clean verdict never gets an
        // ImageReport, so this is the only record of why the model let a borderline photo
        // through.
        $this->moderationLogger->info('Image moderation result', [
            'image_id' => $imageId,
            'categories' => $result['categories'],
            'confidence' => $result['confidence'],
            'explanation' => $result['explanation'],
        ]);

        return $result;
    }

    /** @param array{categories: string[], focalX: float, focalY: float, confidence: string, explanation: ?string} $result */
    public function applyResult(Image $image, array $result): void
    {
        $previousFocal = [$image->getFocalX(), $image->getFocalY()];
        $image->setFocalX($result['focalX']);
        $image->setFocalY($result['focalY']);

        try {
            $this->imageManager->syncOriginalMetadata($image);
        } catch (\Throwable $e) {
            // Leave the entity as it was: a later flush in the same unit of work (e.g. the next
            // image of a ReprocessImagesCommand batch) must not commit values S3 never got.
            [$focalX, $focalY] = $previousFocal;
            $image->setFocalX($focalX);
            $image->setFocalY($focalY);

            throw $e;
        }

        $image->setAnalyzedAt(new \DateTime());

        if ([] === $result['categories']) {
            $image->setEnabled(true);

            return;
        }

        // Reanalysis (ReprocessImagesCommand) can newly flag an already-published photo -- take it
        // back down immediately rather than leaving it live while the report is pending review.
        $image->setEnabled(false);

        if ($this->imageReportRepository->hasUnresolvedReport($image)) {
            $this->moderationLogger->info('Image already has a pending report -- skipping duplicate', [
                'image_id' => $this->imageIdForLogging($image),
            ]);

            return;
        }

        $report = new ImageReport()
            ->setImage($image)
            ->setImageFilename($image->getFilename())
            ->setCoasterName($image->getCoaster()->getName())
            ->setCategories($result['categories'])
            ->setAiConfidence($result['confidence'])
            ->setAiExplanation($result['explanation']);

        $this->entityManager->persist($report);
    }

    private function buildPrompt(): string
    {
        return <<<'PROMPT'
            <task>
            You are moderating the user-submitted photo in the user message, for a roller coaster enthusiast website. The photo
            should show a roller coaster or amusement park. Analyze it for the following:

            1. Off-topic: the photo is not of a coaster or amusement park at all. An atmospheric shot with
               no track/train visible (theming, queue decor, a distant view) is still on-topic as long as
               it's clearly part of a coaster/park setting -- don't require the ride itself to be visible.
            2. Retouched: the photo is heavily filtered/retouched in a way that misrepresents the subject
               (not normal phone-camera processing).
            3. People subject: flag only when a person, rather than the coaster or park, is what the photo
               is about:
               - a face in close-up, large enough that the person is easily identifiable;
               - a selfie (person holding the camera, or looking into it at arm's length), even with a
                 ride visible behind them;
               - a personal or holiday-style photo: one or more people posing or facing the camera, e.g.
                 in front of a ride or somewhere in the park.
               Do NOT flag riders on a train, even in a tight zoom and even when their faces are visible,
               as long as the train/track is what the photo is about; nor crowds, queues, or people who
               are small, distant or incidental in the frame. Zoom level and the number of people visible
               are not the test -- what the photo is about is. If it is borderline, do not flag: answer
               with no category and "medium" confidence instead.
            4. On-ride photo: this is one of the commercial on-ride photos a park sells at the exit of the
               ride (rider(s) mid-ride, usually close-up on their faces/reactions, often with a park logo,
               timestamp, or price/purchase overlay burned in) rather than a photo someone took of the
               coaster itself.

            Also locate the photo's main subject of interest (the coaster/ride/track/station -- whatever a
            human would consider "the point" of the photo) as normalized (x, y) coordinates, where (0, 0)
            is the top-left corner and (1, 1) is the bottom-right corner.

            Give a one-sentence explanation only when you flag a category, otherwise null.
            </task>
            PROMPT;
    }

    /**
     * A "nothing wrong" verdict is only trusted when the model is highly confident; otherwise a
     * human decides. Done here rather than in applyResult() so every caller sees the same
     * categories (ReprocessImagesCommand counts flags from them).
     *
     * @param array{categories: string[], focalX: float, focalY: float, confidence: string, explanation: ?string} $result
     *
     * @return array{categories: string[], focalX: float, focalY: float, confidence: string, explanation: ?string}
     */
    private function flagUncertainVerdict(array $result): array
    {
        if ([] !== $result['categories'] || 'high' === $result['confidence']) {
            return $result;
        }

        $result['categories'] = [ImageReport::CATEGORY_UNCERTAIN];
        $result['explanation'] = \sprintf('No issue flagged, but the model was not highly confident (%s).', $result['confidence']);

        return $result;
    }

    private function imageIdForLogging(Image $image): int|string
    {
        try {
            return $image->getId();
        } catch (\TypeError) {
            return 'unpersisted';
        }
    }
}
