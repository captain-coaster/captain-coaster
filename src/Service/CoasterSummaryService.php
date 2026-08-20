<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
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

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RiddenCoasterRepository $riddenCoasterRepository,
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
    public function generateSummary(Coaster $coaster, ?string $modelKey = null, string $language = 'en'): array
    {
        $analysis = $this->runAnalysis($coaster, $modelKey, $language);

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

        // Reset votes when summary is regenerated since content has changed
        $summary->setPositiveVotes(0);
        $summary->setNegativeVotes(0);
        $summary->setFeedbackRatio(0.0);

        // Clear existing feedback records since they're no longer relevant
        $this->clearSummaryFeedback($summary);

        $this->entityManager->persist($summary);
        $this->entityManager->flush();

        return ['summary' => $summary, 'metadata' => $analysis['metadata']];
    }

    /**
     * Runs the same review analysis as generateSummary() but never persists anything.
     * Used to preview/compare model output (e.g. for model evaluation tooling).
     *
     * @return array{summary: string|null, pros: array<string>, cons: array<string>, metadata: array<string, mixed>|null, reason?: string, review_count?: int, total_review_count?: int, model_key?: string}
     */
    public function previewSummary(Coaster $coaster, ?string $modelKey, string $language): array
    {
        $analysis = $this->runAnalysis($coaster, $modelKey, $language);

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
    private function runAnalysis(Coaster $coaster, ?string $modelKey, string $language): array
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

        $aiAnalysis = $this->analyzeReviews($reviewsForAnalysis, $coaster->getName(), $modelKey, $language);

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', [
                'coaster' => $coaster->getName(),
                'coaster_id' => $coaster->getId(),
                'language' => $language,
                'model_key' => $modelKey,
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
                'resolvedModelKey' => $modelKey ?? BedrockService::DEFAULT_MODEL,
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
    private function analyzeReviews(array $reviews, string $coasterName, ?string $modelKey = null, string $language = 'en'): array
    {
        if (empty($reviews)) {
            return ['summary' => '', 'pros' => [], 'cons' => []];
        }

        // Get coaster entity from first review to access coaster context data
        $coaster = $reviews[0]->getCoaster();

        $prompt = $this->buildPrompt($reviews, $coasterName, $coaster, $language);
        $response = $this->bedrockService->invokeModel($prompt, $modelKey, 2000, 0.5);

        if (!$response['success']) {
            $this->logger->error('Bedrock service error', [
                'coaster' => $coasterName,
                'coaster_id' => $coaster->getId(),
                'language' => $language,
                'model_key' => $modelKey,
                'error' => $response['error'] ?? 'Unknown error',
                'error_code' => $response['error_code'] ?? null,
                'review_count' => \count($reviews),
                'metadata' => $response['metadata'],
            ]);

            return ['summary' => '', 'pros' => [], 'cons' => [], 'metadata' => $response['metadata']];
        }

        return array_merge(
            $this->parseAiResponse($response['content'] ?? ''),
            ['metadata' => $response['metadata']]
        );
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
     * Builds the AI prompt for review analysis with enhanced source data and security sanitization.
     *
     * @param array<int, RiddenCoaster> $riddenCoasters
     */
    private function buildPrompt(array $riddenCoasters, string $coasterName, ?Coaster $coaster, string $language = 'en'): string
    {
        // Sanitize coaster name to prevent prompt injection
        $sanitizedName = preg_replace('/[^\w\s-]/', '', $coasterName);
        $reviewCount = \count($riddenCoasters);

        // Language-specific instructions
        $languageNames = [
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
        ];
        $languageName = $languageNames[$language] ?? 'English';
        $outputLanguageInstruction = "Write the summary and pros/cons in natural, fluent {$languageName}, as if written by a native speaker enthusiast. Some source reviews below may be written in other languages — read them for content and sentiment, but always respond in {$languageName}.";

        $prompt = "You are an expert roller coaster analyst with deep knowledge of ride experiences and enthusiast terminology. Your task is to analyze rider reviews for {$sanitizedName} and create an objective, balanced summary that helps future riders make informed decisions.\n\n";

        $prompt .= "<analysis_task>\n";
        $prompt .= "Analyze the following {$reviewCount} reviews to create:\n\n";
        $prompt .= "1. SUMMARY: A truthful summary that reflects the actual consensus from reviews\n";
        $prompt .= "   - Use the rating distribution below to calibrate tone and gauge consensus, but never quote exact numbers or percentages in the summary\n";
        $prompt .= "   - MUST contain between 3 and 5 sentences\n\n";
        $prompt .= "2. PROS/CONS: List the most frequently mentioned aspects (MAX 4 words each), only if raised by multiple reviewers\n";
        $prompt .= "   - Scale the count to sentiment: highly rated -> 3-5 pros / 1-2 cons; poorly rated -> 1-2 pros / 3-5 cons; empty arrays are fine if unsupported by review content\n\n";
        $prompt .= "IMPORTANT GUIDELINES:\n";
        $prompt .= "- {$outputLanguageInstruction}\n";
        $prompt .= "- Never mention safety, legal, maintenance, construction or security issues\n";
        $prompt .= "- Be honest about the actual sentiment - don't force balance if reviews are overwhelmingly positive or negative\n";
        $prompt .= "</analysis_task>\n\n";

        // Coaster context section with enhanced formatting and rating distribution
        if ($coaster && $coaster->getStatus()) {
            $prompt .= "<coaster_context>\n";
            $prompt .= "Coaster: {$sanitizedName}\n";
            $prompt .= "Status: {$coaster->getStatus()->getName()}\n";

            if ($coaster->getAverageRating() && $coaster->getTotalRatings() > 0) {
                // Ratings are on a 0-5 scale (see RiddenCoasterRepository::updateAverageRatings())
                $ratingPercent = round(((float) $coaster->getAverageRating() / 5) * 100, 1);
                $prompt .= "Community Rating: {$ratingPercent}% based on {$coaster->getTotalRatings()} ratings\n";

                // Add rating distribution analysis
                $ratingDistribution = $this->calculateRatingDistribution($riddenCoasters);
                $prompt .= "Rating Distribution:\n";
                $prompt .= "- Positive (4-5 stars): {$ratingDistribution['positive']}%\n";
                $prompt .= "- Neutral (3 stars): {$ratingDistribution['neutral']}%\n";
                $prompt .= "- Negative (1-2 stars): {$ratingDistribution['negative']}%\n";
            }
            $prompt .= "Reviews to analyze: {$reviewCount}\n";
            $prompt .= "</coaster_context>\n\n";
        }

        // Reviews section - without individual ratings to avoid bias
        $prompt .= "<review_data>\n";
        foreach ($riddenCoasters as $index => $riddenCoaster) {
            $prompt .= "{$riddenCoaster->getReview()}\n\n";
        }
        $prompt .= "</review_data>\n\n";

        // Restated right after the (often very large) review data, close to generation,
        // since instructions stated only once before tens of thousands of tokens of
        // reviews are easy for the model to lose track of.
        $prompt .= "<output_format>\n";
        $prompt .= "Reminder: respond in {$languageName}; summary is 3-5 sentences; never quote exact numbers or percentages.\n";
        $prompt .= "Respond with valid JSON in this exact format, parseable directly:\n";
        $prompt .= "{\n";
        $prompt .= "  \"summary\": \"Your analysis in {$languageName} reflecting the actual review consensus\",\n";
        $prompt .= "  \"pros\": [\"positive aspect 1\", \"positive aspect 2\"],\n";
        $prompt .= "  \"cons\": [\"concern 1\"]\n";
        $prompt .= "}\n";
        $prompt .= '</output_format>';

        return $prompt;
    }

    /**
     * Parses AI response with security validation and data sanitization.
     *
     * @return array{summary: string, pros: array<string>, cons: array<string>}
     */
    private function parseAiResponse(string $response): array
    {
        try {
            // Remove reasoning tags if present
            $cleanedResponse = preg_replace('/<reasoning>.*?<\/reasoning>\s*/s', '', $response);
            $cleanedResponse = trim($cleanedResponse);

            // Extract JSON from response
            if (preg_match('/\{.*\}/s', $cleanedResponse, $matches)) {
                $json = json_decode($matches[0], true, 10, \JSON_THROW_ON_ERROR);
                if ($json && isset($json['summary']) && \is_string($json['summary'])) {
                    return [
                        'summary' => trim($json['summary']),
                        'pros' => \is_array($json['pros'] ?? []) ? \array_slice($json['pros'], 0, 5) : [],
                        'cons' => \is_array($json['cons'] ?? []) ? \array_slice($json['cons'], 0, 5) : [],
                    ];
                }
            }
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to parse AI response JSON', [
                'error' => $e->getMessage(),
                'json_error_code' => $e->getCode(),
                'response_content' => substr($response, 0, 500), // Log first 500 chars for debugging
                'response_length' => \strlen($response),
            ]);
        }

        return ['summary' => '', 'pros' => [], 'cons' => []];
    }
}
