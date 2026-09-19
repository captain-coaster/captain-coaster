<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\ImageReport;
use App\Repository\ImageReportRepository;
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
    private const MODEL_KEY = 'gpt-5.6-luna';

    // The model sometimes emits a <reasoning> block (stripped in parseResponse()) ahead of the
    // JSON -- 500 was observed hitting stop_reason=max_tokens mid-JSON on a real image, which
    // silently fails analysis (and burns a paid call) since the truncated response can't parse.
    private const MAX_TOKENS = 800;

    private const VALID_CATEGORIES = [
        ImageReport::CATEGORY_OFFTOPIC,
        ImageReport::CATEGORY_WATERMARK,
        ImageReport::CATEGORY_RETOUCHED,
        ImageReport::CATEGORY_PEOPLE_SUBJECT,
        ImageReport::CATEGORY_ONRIDE_PHOTO,
    ];

    private const VALID_CONFIDENCE_LEVELS = ['low', 'medium', 'high'];

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

    /** @return array{categories: string[], focalX: float, focalY: float, confidence: ?string, explanation: ?string}|null */
    public function analyze(Image $image): ?array
    {
        try {
            // The 1440x1440 "lightbox" derivative is already generated/cached by the existing
            // pipeline for other consumers (User/images.html.twig, Coaster/_image_panel.html.twig)
            // and is letterboxed (INSIDE_FIT_SIZES in captain-infra), not cropped -- the whole
            // photo, just downscaled, which is what moderation/focal-point detection needs. This
            // avoids adding any PHP-side image manipulation just to bound the Bedrock payload size.
            // jpg, not avif: Bedrock's Converse API ImageFormat only accepts png/jpeg/gif/webp.
            $url = $this->pictureUrlSigner->sign($image->getFilename(), 1440, 1440, 'jpg');
            $imageBytes = $this->httpClient->request('GET', $url)->getContent();
        } catch (\Throwable $e) {
            $this->moderationLogger->error('Image moderation could not fetch the lightbox derivative', [
                'image_id' => $this->imageIdForLogging($image),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $response = $this->bedrockService->invokeVisionModel($this->buildPrompt(), $imageBytes, 'jpeg', self::MODEL_KEY, self::MAX_TOKENS, 0.3);

        if (!$response['success']) {
            $this->moderationLogger->error('Image moderation Bedrock call failed', [
                'image_id' => $this->imageIdForLogging($image),
                'error' => $response['error'] ?? 'unknown',
            ]);

            return null;
        }

        $parsed = $this->parseResponse($response['content'] ?? '');
        if (null === $parsed) {
            $this->moderationLogger->warning('Image moderation response could not be parsed', [
                'image_id' => $this->imageIdForLogging($image),
                'content' => $response['content'] ?? '',
            ]);
        } else {
            $parsed = $this->flagUncertainVerdict($parsed);

            // Logged unconditionally (not just on flag) -- a clean verdict never gets an
            // ImageReport, so this is the only record of why the model let a borderline photo
            // through.
            $this->moderationLogger->info('Image moderation result', [
                'image_id' => $this->imageIdForLogging($image),
                'categories' => $parsed['categories'],
                'confidence' => $parsed['confidence'],
                'explanation' => $parsed['explanation'],
            ]);
        }

        return $parsed;
    }

    /** @param array{categories: string[], focalX: float, focalY: float, confidence: ?string, explanation: ?string} $result */
    public function applyResult(Image $image, array $result): void
    {
        $image->setFocalX($result['focalX']);
        $image->setFocalY($result['focalY']);
        $image->setAnalyzedAt(new \DateTime());

        $this->imageManager->writeFocalPointMetadata($image);

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
            You are moderating a user-submitted photo for a roller coaster enthusiast website. The photo
            should show a roller coaster or amusement park. Analyze it for the following:

            1. Off-topic: the photo is not of a coaster or amusement park at all. An atmospheric shot with
               no track/train visible (theming, queue decor, a distant view) is still on-topic as long as
               it's clearly part of a coaster/park setting -- don't require the ride itself to be visible.
            2. Watermark: the photo has a visible watermark, logo overlay, or added text (not something
               physically present in the scene, like a sign) -- EXCEPT a "CAPTAIN COASTER" text/logo
               watermark in a corner, which this site adds itself when the uploader opts in and must
               never be flagged.
            3. Retouched: the photo is heavily filtered/retouched in a way that misrepresents the subject
               (not normal phone-camera processing).
            4. People subject: judge by how much of the frame people take up, not by what they're doing.
               Flag when one or more people are large/prominent enough (close-up, foreground, filling a
               significant portion of the image) that they read as the photo's subject rather than the
               coaster/park -- whether posed, mid-stride, or otherwise doesn't matter. This is a thin
               line, since park photos naturally have people in them: small or distant figures elsewhere
               in the frame are normal and must NOT be flagged. This is not a personal holiday photo
               book -- when in doubt about whether people are prominent enough, flag it.
            5. On-ride photo: this is one of the commercial on-ride photos a park sells at the exit of the
               ride (rider(s) mid-ride, usually close-up on their faces/reactions, often with a park logo,
               timestamp, or price/purchase overlay burned in) rather than a photo someone took of the
               coaster itself.

            Also locate the photo's main subject of interest (the coaster/ride/track/station -- whatever a
            human would consider "the point" of the photo) as normalized (x, y) coordinates, where (0, 0)
            is the top-left corner and (1, 1) is the bottom-right corner.
            </task>

            <output_format>
            Respond with strict JSON only, no other text:
            {"categories": string[] (any of "offtopic", "watermark", "retouched", "people_subject",
            "onride_photo"; empty array if none apply), "focal_x": number, "focal_y": number,
            "confidence": "low"|"medium"|"high", "explanation": string (one sentence, only if categories is
            non-empty, else null)}
            </output_format>
            PROMPT;
    }

    /**
     * A "nothing wrong" verdict is only trusted when the model is highly confident; otherwise a
     * human decides. Done here rather than in applyResult() so every caller sees the same
     * categories (ReprocessImagesCommand counts flags from them).
     *
     * @param array{categories: string[], focalX: float, focalY: float, confidence: ?string, explanation: ?string} $result
     *
     * @return array{categories: string[], focalX: float, focalY: float, confidence: ?string, explanation: ?string}
     */
    private function flagUncertainVerdict(array $result): array
    {
        if ([] !== $result['categories'] || 'high' === $result['confidence']) {
            return $result;
        }

        $result['categories'] = [ImageReport::CATEGORY_UNCERTAIN];
        $result['explanation'] = \sprintf('No issue flagged, but the model was not highly confident (%s).', $result['confidence'] ?? 'unknown');

        return $result;
    }

    /** @return array{categories: string[], focalX: float, focalY: float, confidence: ?string, explanation: ?string}|null */
    private function parseResponse(string $response): ?array
    {
        $response = preg_replace('/<reasoning>.*?<\/reasoning>/s', '', $response) ?? $response;

        if (!preg_match('/\{.*\}/s', $response, $matches)) {
            return null;
        }

        try {
            $decoded = json_decode($matches[0], true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded) || !isset($decoded['categories'], $decoded['focal_x'], $decoded['focal_y'])) {
            return null;
        }

        if (!\is_array($decoded['categories'])) {
            return null;
        }

        $categories = [];
        foreach ($decoded['categories'] as $category) {
            if (\is_string($category) && \in_array($category, self::VALID_CATEGORIES, true)) {
                $categories[] = $category;
            }
        }

        if (!is_numeric($decoded['focal_x']) || !is_numeric($decoded['focal_y'])) {
            return null;
        }

        $confidence = $decoded['confidence'] ?? null;
        if (!\in_array($confidence, self::VALID_CONFIDENCE_LEVELS, true)) {
            $confidence = null;
        }

        $explanation = $decoded['explanation'] ?? null;
        $explanation = \is_string($explanation) ? trim($explanation) : null;

        return [
            'categories' => $categories,
            'focalX' => max(0.0, min(1.0, (float) $decoded['focal_x'])),
            'focalY' => max(0.0, min(1.0, (float) $decoded['focal_y'])),
            'confidence' => $confidence,
            'explanation' => '' !== $explanation ? $explanation : null,
        ];
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
