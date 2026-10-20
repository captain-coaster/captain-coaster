<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\RiddenCoaster;
use App\Repository\CoasterSummaryRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use App\Service\Ai\BedrockService;
use App\Service\Ai\PromptNameSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating and managing AI-powered coaster summaries.
 *
 * This service analyzes coaster reviews using AWS Bedrock AI models to generate
 * summaries with pros/cons lists. It handles review collection, AI analysis,
 * and summary persistence with language support.
 *
 * Each language summary is now independent - deleting one language summary
 * does not affect other languages. Feedback records are still cascade-deleted
 * when their associated summary is removed.
 */
class CoasterSummaryService
{
    /** Maximum number of reviews to analyze per coaster */
    private const MAX_REVIEWS_FOR_ANALYSIS = 600;

    /**
     * Minimum reviews required before generating a summary, counted across all languages
     * combined (same-language reviews plus what backfill could supply) - this is a "is
     * there enough to analyze at all" floor, not a same-language-only requirement.
     */
    public const MIN_REVIEWS_REQUIRED = 20;

    /**
     * Minimum same-language reviews required, regardless of how much backfill is
     * available. Below this, a summary would be almost entirely other-language content
     * translated into the target language rather than reflecting a genuine same-language
     * audience - low, since backfill plus a capable model already produces fluent,
     * accurate output even from a minority-language sample (validated against production
     * data), but not zero.
     */
    public const MIN_NATIVE_REVIEWS_REQUIRED = 5;

    /**
     * Below this many same-language reviews, backfill the analysis set with reviews from
     * other languages so thin-language coasters still get a representative sample. The
     * summary is still always written in the target language regardless of source mix.
     */
    private const REPRESENTATIVE_SAMPLE_FLOOR = 100;

    /** Strict mode: the answer always matches it, so it is the only validation. */
    private const OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'pros' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
            'cons' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
        ],
        'required' => ['summary', 'pros', 'cons'],
        'additionalProperties' => false,
    ];

    private const LANGUAGE_NAMES = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RiddenCoasterRepository $riddenCoasterRepository,
        private CoasterSummaryRepository $coasterSummaryRepository,
        private BedrockService $bedrockService,
        private LoggerInterface $logger
    ) {
    }

    /** Gets count of reviews with text content for a coaster, scoped to a language */
    public function getReviewCount(Coaster $coaster, string $language): int
    {
        return $this->riddenCoasterRepository->countCoasterReviewsWithTextByLanguage($coaster, $language);
    }

    /** Clears all feedback records for a summary when it's regenerated */
    private function clearSummaryFeedback(CoasterSummary $summary): void
    {
        // Only clear feedback if summary has an ID (already persisted)
        if (!$summary->getId()) {
            return;
        }

        $this->entityManager->createQueryBuilder()
            ->delete('App\Entity\SummaryFeedback', 'sf')
            ->where('sf.summary = :summaryId')
            ->setParameter('summaryId', $summary->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * Generates an AI summary for a coaster based on its reviews.
     *
     * @return array{summary: CoasterSummary|null, metadata: array<string, mixed>|null, reason?: string, review_count?: int}
     */
    public function generateSummary(Coaster $coaster, ?AiModel $model = null, string $language = 'en'): array
    {
        $analysis = $this->runAnalysis($coaster, $model, $language);

        if (!isset($analysis['result'])) {
            if (isset($analysis['review_count'])) {
                return ['summary' => null, 'metadata' => $analysis['metadata'], 'reason' => $analysis['status'], 'review_count' => $analysis['review_count']];
            }

            return ['summary' => null, 'metadata' => $analysis['metadata'], 'reason' => $analysis['status']];
        }

        $summary = $this->findOrCreateSummary($coaster, $language);
        $summary->setSummary($analysis['result']['aiAnalysis']['summary']);
        $summary->setDynamicPros($analysis['result']['aiAnalysis']['pros']);
        $summary->setDynamicCons($analysis['result']['aiAnalysis']['cons']);
        $summary->setReviewsAnalyzed($analysis['result']['reviewCount']);
        $summary->setLanguage($language);
        $summary->setAiModel($analysis['result']['resolvedModelKey']);

        // Gedmo's on:'change' strategy only updates existing rows, not new inserts
        if (null === $summary->getId()) {
            $summary->setRegeneratedAt(new \DateTime());
        }

        // Reset votes when summary is regenerated since content has changed
        $summary->setPositiveVotes(0);
        $summary->setNegativeVotes(0);
        $summary->setFeedbackRatio(0.0);

        // Clear existing feedback records since they're no longer relevant
        $this->clearSummaryFeedback($summary);

        $this->entityManager->persist($summary);
        $this->entityManager->flush();

        $this->coasterSummaryRepository->clearCacheFor($coaster, $language);

        return ['summary' => $summary, 'metadata' => $analysis['metadata']];
    }

    /**
     * Runs the same review analysis as generateSummary() but never persists anything.
     * Used to preview/compare model output (e.g. for model evaluation tooling).
     *
     * @return array{summary: string|null, pros: array<string>, cons: array<string>, metadata: array<string, mixed>|null, reason?: string, review_count?: int, total_review_count?: int, model_key?: string}
     */
    public function previewSummary(Coaster $coaster, ?AiModel $model, string $language): array
    {
        $analysis = $this->runAnalysis($coaster, $model, $language, preview: true);

        if (!isset($analysis['result'])) {
            if (isset($analysis['review_count'])) {
                return ['summary' => null, 'pros' => [], 'cons' => [], 'metadata' => $analysis['metadata'], 'reason' => $analysis['status'], 'review_count' => $analysis['review_count']];
            }

            return ['summary' => null, 'pros' => [], 'cons' => [], 'metadata' => $analysis['metadata'], 'reason' => $analysis['status']];
        }

        return [
            'summary' => $analysis['result']['aiAnalysis']['summary'],
            'pros' => $analysis['result']['aiAnalysis']['pros'],
            'cons' => $analysis['result']['aiAnalysis']['cons'],
            'metadata' => $analysis['metadata'],
            'review_count' => $analysis['result']['reviewCount'],
            'total_review_count' => $analysis['result']['totalReviewCount'],
            'model_key' => $analysis['result']['resolvedModelKey'],
        ];
    }

    /**
     * Shared review-fetch + AI-analysis step used by both generateSummary() and previewSummary().
     *
     * @return array{status: 'ok'|'insufficient_reviews'|'ai_error', metadata: array<string, mixed>|null, review_count?: int, result?: array{aiAnalysis: array{summary: string, pros: array<string>, cons: array<string>}, reviewCount: int, totalReviewCount: int, resolvedModelKey: string}}
     */
    private function runAnalysis(Coaster $coaster, ?AiModel $model, string $language, bool $preview = false): array
    {
        $nativeReviewCount = $this->getReviewCount($coaster, $language);

        if ($nativeReviewCount < self::MIN_NATIVE_REVIEWS_REQUIRED) {
            $this->logger->info('Not enough same-language reviews to generate summary', ['coaster' => $coaster->getName(), 'language' => $language, 'reviews' => $nativeReviewCount]);

            return ['status' => 'insufficient_reviews', 'metadata' => null, 'review_count' => $nativeReviewCount];
        }

        // Enough of a genuine same-language base - now check there's enough content
        // overall once backfill from other languages is taken into account.
        $totalReviewCount = $this->riddenCoasterRepository->countAllReviewsWithText($coaster);

        if ($totalReviewCount < self::MIN_REVIEWS_REQUIRED) {
            $this->logger->info('Not enough reviews (any language) to generate summary', ['coaster' => $coaster->getName(), 'language' => $language, 'reviews' => $totalReviewCount]);

            return ['status' => 'insufficient_reviews', 'metadata' => null, 'review_count' => $totalReviewCount];
        }

        $primaryReviews = $this->riddenCoasterRepository->getCoasterReviewsWithTextByLanguage($coaster, $language, self::MAX_REVIEWS_FOR_ANALYSIS);
        $primaryReviewCount = \count($primaryReviews);

        // Thin same-language sample: backfill with other-language reviews for a more
        // representative consensus. The prompt still forces the output language.
        $reviewsForAnalysis = $primaryReviews;
        if ($primaryReviewCount < self::REPRESENTATIVE_SAMPLE_FLOOR) {
            $backfillLimit = min(self::REPRESENTATIVE_SAMPLE_FLOOR, self::MAX_REVIEWS_FOR_ANALYSIS) - $primaryReviewCount;
            $reviewsForAnalysis = [...$primaryReviews, ...$this->riddenCoasterRepository->getCoasterReviewsWithTextExcludingLanguage($coaster, $language, $backfillLimit)];
        }

        $aiAnalysis = $this->analyzeReviews($reviewsForAnalysis, $coaster, $model, $language, $preview);

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', [
                'coaster' => $coaster->getName(),
                'coaster_id' => $coaster->getId(),
                'language' => $language,
                'model_key' => ($model ?? AiTask::CoasterSummary->model())->value,
                'review_count' => \count($reviewsForAnalysis),
                'metadata' => $aiAnalysis['metadata'] ?? null,
            ]);

            return ['status' => 'ai_error', 'metadata' => $aiAnalysis['metadata'] ?? null];
        }

        return [
            'status' => 'ok',
            'metadata' => $aiAnalysis['metadata'] ?? null,
            'result' => [
                'aiAnalysis' => $aiAnalysis,
                // Same-language count only: this is what's persisted and what the
                // regeneration-growth threshold in shouldUpdateSummary() compares against.
                'reviewCount' => $primaryReviewCount,
                'totalReviewCount' => \count($reviewsForAnalysis),
                'resolvedModelKey' => ($model ?? AiTask::CoasterSummary->model())->value,
            ],
        ];
    }

    /**
     * Determines if a coaster summary should be updated.
     * Updates are needed if no summary exists yet, or enough new same-language reviews
     * have arrived since the last generation. There's no time-based expiry: a summary
     * with nothing new to say doesn't need regenerating, and this avoids resetting user
     * feedback votes for no content-relevant reason. Use the admin "regenerate" action
     * for cases where a coaster's status/rating context has changed without new reviews.
     */
    public function shouldUpdateSummary(Coaster $coaster, string $language = 'en'): bool
    {
        $summary = $this->entityManager->getRepository(CoasterSummary::class)
            ->findOneBy(['coaster' => $coaster, 'language' => $language]);

        if (!$summary) {
            return true;
        }

        $currentReviewCount = $this->getReviewCount($coaster, $language);
        $analyzedCount = $summary->getReviewsAnalyzed();
        $threshold = max(self::MIN_REVIEWS_REQUIRED, (int) ($analyzedCount * 0.2));

        return ($currentReviewCount - $analyzedCount) >= $threshold;
    }

    private function findOrCreateSummary(Coaster $coaster, string $language = 'en'): CoasterSummary
    {
        $summary = $this->entityManager->getRepository(CoasterSummary::class)
            ->findOneBy(['coaster' => $coaster, 'language' => $language]);

        if (!$summary) {
            $summary = new CoasterSummary();
            $summary->setCoaster($coaster);
            $summary->setLanguage($language);
        }

        return $summary;
    }

    /**
     * Analyzes reviews using AI.
     *
     * @param array<int, RiddenCoaster> $reviews
     *
     * @return array{summary: string, pros: array<string>, cons: array<string>, metadata?: array<string, mixed>}
     */
    private function analyzeReviews(array $reviews, Coaster $coaster, ?AiModel $model, string $language, bool $preview): array
    {
        if (empty($reviews)) {
            return ['summary' => '', 'pros' => [], 'cons' => []];
        }

        $response = $this->bedrockService->invoke(
            AiTask::CoasterSummary,
            $this->buildInstructions($language),
            $this->buildInput($reviews, $coaster, $language),
            self::OUTPUT_SCHEMA,
            // A preview's cost is logged without a subject, so it never counts as the summary's.
            subjectId: $preview ? null : $coaster->getId(),
            language: $language,
            model: $model,
        );

        if (!$response['success']) {
            $this->logger->error('Bedrock service error', [
                'coaster' => $coaster->getName(),
                'coaster_id' => $coaster->getId(),
                'language' => $language,
                'error' => $response['error'],
                'error_code' => $response['error_code'],
                'review_count' => \count($reviews),
                'metadata' => $response['metadata'],
            ]);

            return ['summary' => '', 'pros' => [], 'cons' => [], 'metadata' => $response['metadata']];
        }

        $data = $response['data'];

        return [
            'summary' => trim((string) $data['summary']),
            'pros' => array_map(strval(...), (array) $data['pros']),
            'cons' => array_map(strval(...), (array) $data['cons']),
            'metadata' => $response['metadata'],
        ];
    }

    /**
     * Calculates rating distribution to help AI understand sentiment.
     *
     * @param array<int, RiddenCoaster> $riddenCoasters
     *
     * @return array{positive: float, neutral: float, negative: float}
     */
    private function calculateRatingDistribution(array $riddenCoasters): array
    {
        $total = \count($riddenCoasters);
        if (0 === $total) {
            return ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }

        $positive = 0;
        $neutral = 0;
        $negative = 0;

        foreach ($riddenCoasters as $riddenCoaster) {
            $rating = $riddenCoaster->getValue();
            if (null === $rating) {
                continue;
            }

            if ($rating >= 4.0) {
                ++$positive;
            } elseif ($rating >= 3.0) {
                ++$neutral;
            } else {
                ++$negative;
            }
        }

        return [
            'positive' => round(($positive / $total) * 100, 1),
            'neutral' => round(($neutral / $total) * 100, 1),
            'negative' => round(($negative / $total) * 100, 1),
        ];
    }

    /**
     * The fixed part of the prompt, one per language: it goes in the system prompt so it forms a
     * cacheable prefix across coasters.
     */
    private function buildInstructions(string $language): string
    {
        $languageName = self::LANGUAGE_NAMES[$language] ?? 'English';

        return <<<PROMPT
            You are an expert roller coaster analyst with deep knowledge of ride experiences and enthusiast terminology. Your task is to analyze the rider reviews of the coaster given in the user message and create an objective, balanced summary that helps future riders make informed decisions.

            <analysis_task>
            Analyze the reviews to create:

            1. SUMMARY: A truthful summary that reflects the actual consensus from reviews
               - Use the rating distribution to calibrate tone and gauge consensus, but never quote exact numbers or percentages in the summary
               - MUST contain between 3 and 5 sentences
               - Open with what sets this coaster apart, not with a statement about the reviews or the consensus ("Reviews are...", "X is overwhelmingly praised...")
               - End with one sentence of advice from experienced riders to someone about to ride it for the first time (which row, who will love or dislike it, when to ride, whether it is worth the wait), drawn from what the reviews say

            2. PROS/CONS: List the most frequently mentioned aspects (MAX 4 words each), only if raised by multiple reviewers
               - Scale the count to sentiment: highly rated -> 3-5 pros / 1-2 cons; poorly rated -> 1-2 pros / 3-5 cons; empty arrays are fine if unsupported by review content

            3. CHANGES OVER TIME: Each review starts with the year the coaster was ridden, in brackets
               - Describe the coaster as it rides today. When recent reviews report a change (new trains, retrack, reprofiling, added trims or restraints), prefer them over older reviews about the same aspect, and mention the change in the summary

            IMPORTANT GUIDELINES:
            - Write the summary and pros/cons in natural, fluent {$languageName}, as if written by a native speaker enthusiast. Some source reviews may be written in other languages — read them for content and sentiment, but always respond in {$languageName}. When a source review uses enthusiast slang or jargon that has no natural equivalent in {$languageName}, convey the sensation it describes rather than translating it word-for-word.
            - Never mention safety, legal, maintenance, construction or security issues
            - Be honest about the actual sentiment - don't force balance if reviews are overwhelmingly positive or negative
            </analysis_task>
            PROMPT;
    }

    /**
     * The per-coaster part of the prompt: context, then the reviews.
     *
     * @param array<int, RiddenCoaster> $riddenCoasters
     */
    private function buildInput(array $riddenCoasters, Coaster $coaster, string $language): string
    {
        $sanitizedName = PromptNameSanitizer::sanitize($coaster->getName());
        $reviewCount = \count($riddenCoasters);
        $languageName = self::LANGUAGE_NAMES[$language] ?? 'English';

        $input = "<coaster_context>\n";
        $input .= "Coaster: {$sanitizedName}\n";
        // Several coasters share a name (two Iron Rattlers): park and manufacturer tell them apart.
        if ($coaster->getPark()) {
            $input .= 'Park: '.PromptNameSanitizer::sanitize($coaster->getPark()->getName())."\n";
        }
        if ($coaster->getManufacturer()) {
            $input .= 'Manufacturer: '.PromptNameSanitizer::sanitize($coaster->getManufacturer()->getName())."\n";
        }

        if ($coaster->getStatus()) {
            $input .= "Status: {$coaster->getStatus()->getName()}\n";
        }

        if ($coaster->getAverageRating() && $coaster->getTotalRatings() > 0) {
            // Ratings are on a 0-5 scale (see RiddenCoasterRepository::updateAverageRatings())
            $ratingPercent = round(((float) $coaster->getAverageRating() / 5) * 100, 1);
            $input .= "Community Rating: {$ratingPercent}% based on {$coaster->getTotalRatings()} ratings\n";

            $ratingDistribution = $this->calculateRatingDistribution($riddenCoasters);
            $input .= "Rating Distribution:\n";
            $input .= "- Positive (4-5 stars): {$ratingDistribution['positive']}%\n";
            $input .= "- Neutral (3 stars): {$ratingDistribution['neutral']}%\n";
            $input .= "- Negative (1-2 stars): {$ratingDistribution['negative']}%\n";
        }
        $input .= "Reviews to analyze: {$reviewCount}\n";
        $input .= "</coaster_context>\n\n";

        // Without individual ratings, to avoid bias.
        $input .= "<review_data>\n";
        foreach ($riddenCoasters as $riddenCoaster) {
            $year = ($riddenCoaster->getRiddenAt() ?? $riddenCoaster->getCreatedAt())?->format('Y') ?? '?';
            $input .= "[{$year}] {$riddenCoaster->getReview()}\n\n";
        }
        $input .= "</review_data>\n\n";

        // Restated after the (often very large) review data, close to generation: instructions
        // stated only before tens of thousands of tokens of reviews are easy to lose track of.
        $input .= "Reminder: respond in {$languageName}; summary is 3-5 sentences ending with advice for a first-time rider; never quote exact numbers or percentages.";

        return $input;
    }
}
