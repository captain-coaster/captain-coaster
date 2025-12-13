<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\RiddenCoasterRepository;
use App\Repository\VocabularyGuideRepository;
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

    /** Minimum reviews required before generating a summary */
    public const MIN_REVIEWS_REQUIRED = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RiddenCoasterRepository $riddenCoasterRepository,
        private VocabularyGuideRepository $vocabularyGuideRepository,
        private BedrockService $bedrockService,
        private LoggerInterface $logger
    ) {
    }

    /** Gets count of reviews with text content for a coaster */
    public function getReviewCount(Coaster $coaster): int
    {
        return $this->riddenCoasterRepository->countCoasterReviewsWithText($coaster);
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

    /** Generates an AI summary for a coaster based on its reviews */
    public function generateSummary(Coaster $coaster, ?string $modelKey = null, string $language = 'en'): array
    {
        $currentReviewCount = $this->getReviewCount($coaster);

        if ($currentReviewCount < self::MIN_REVIEWS_REQUIRED) {
            $this->logger->info('Not enough reviews to generate summary', ['coaster' => $coaster->getName(), 'reviews' => $currentReviewCount]);

            return [
                'summary' => null,
                'metadata' => null,
                'reason' => 'insufficient_reviews',
                'review_count' => $currentReviewCount,
            ];
        }

        $reviewsWithText = $this->riddenCoasterRepository->getCoasterReviewsWithText($coaster, self::MAX_REVIEWS_FOR_ANALYSIS);
        $reviewCount = \count($reviewsWithText);

        $aiAnalysis = $this->analyzeReviews($reviewsWithText, $coaster->getName(), $modelKey, $language);

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', [
                'coaster' => $coaster->getName(),
                'coaster_id' => $coaster->getId(),
                'language' => $language,
                'model_key' => $modelKey,
                'review_count' => $reviewCount,
                'metadata' => $aiAnalysis['metadata'] ?? null,
            ]);

            return [
                'summary' => null,
                'metadata' => $aiAnalysis['metadata'] ?? null,
                'reason' => 'ai_error',
            ];
        }

        $summary = $this->findOrCreateSummary($coaster, $language);
        $summary->setSummary($aiAnalysis['summary']);
        $summary->setDynamicPros($aiAnalysis['pros']);
        $summary->setDynamicCons($aiAnalysis['cons']);
        $summary->setReviewsAnalyzed($reviewCount);
        $summary->setLanguage($language);

        // Reset votes when summary is regenerated since content has changed
        $summary->setPositiveVotes(0);
        $summary->setNegativeVotes(0);
        $summary->setFeedbackRatio(0.0);

        // Clear existing feedback records since they're no longer relevant
        $this->clearSummaryFeedback($summary);

        $this->entityManager->persist($summary);
        $this->entityManager->flush();

        return ['summary' => $summary, 'metadata' => $aiAnalysis['metadata']];
    }

    /**
     * Determines if a coaster summary should be updated
     * Updates are needed if no summary exists, 20% more reviews, or summary is 180+ days old.
     */
    public function shouldUpdateSummary(Coaster $coaster, string $language = 'en'): bool
    {
        $summary = $this->entityManager->getRepository(CoasterSummary::class)
            ->findOneBy(['coaster' => $coaster, 'language' => $language]);

        // No existing summary and enough reviews to create one
        if (!$summary) {
            return true;
        }

        // Check if summary is stale first (fastest check)
        if ($summary->getUpdatedAt() < new \DateTime('-180 days')) {
            return true;
        }

        // Check for 20% more reviews with minimum threshold
        $currentReviewCount = $this->getReviewCount($coaster);
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

    private function analyzeReviews(array $reviews, string $coasterName, ?string $modelKey = null, string $language = 'en'): array
    {
        if (empty($reviews)) {
            return ['summary' => '', 'pros' => [], 'cons' => []];
        }

        // Get coaster entity from first review to access coaster context data
        $coaster = $reviews[0]->getCoaster();

        $prompt = $this->buildPrompt($reviews, $coasterName, $coaster, $language);
        $response = $this->bedrockService->invokeModel($prompt, $modelKey, 1000, 0.5, true);

        if (!$response['success']) {
            $this->logger->error('Bedrock service error', [
                'coaster' => $coasterName,
                'coaster_id' => $coaster?->getId(),
                'language' => $language,
                'model_key' => $modelKey,
                'error' => $response['error'],
                'error_code' => $response['error_code'] ?? null,
                'review_count' => \count($reviews),
                'metadata' => $response['metadata'] ?? null,
            ]);

            return ['summary' => '', 'pros' => [], 'cons' => [], 'metadata' => $response['metadata'] ?? null];
        }

        return array_merge(
            $this->parseAiResponse($response['content']),
            ['metadata' => $response['metadata']]
        );
    }

    /** Calculates rating distribution to help AI understand sentiment */
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

    /** Gets vocabulary guide content for a language, with graceful handling of missing guides */
    private function getVocabularyGuide(string $language): ?string
    {
        $vocabularyGuide = $this->vocabularyGuideRepository->findByLanguage($language);

        if ($vocabularyGuide) {
            return $vocabularyGuide->getContent();
        }

        // Log warning for missing vocabulary guides in non-English languages
        if ('en' !== $language) {
            $this->logger->warning('No vocabulary guide found for language', [
                'language' => $language,
            ]);
        }

        return null;
    }

    /** Builds the AI prompt for review analysis with enhanced source data and security sanitization */
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
        $outputLanguageInstruction = 'en' === $language ? '' : " Write the summary and pros/cons in natural, fluent {$languageName} as if written by a native speaker enthusiast.";

        // Enhanced role definition for Nova 2 Lite
        $prompt = "You are an expert roller coaster analyst with deep knowledge of ride experiences and enthusiast terminology. Your task is to analyze rider reviews for {$sanitizedName} and create an objective, balanced summary that helps future riders make informed decisions.\n\n";

        // Enhanced instructions with better structure for Nova 2 Lite
        $prompt .= "<analysis_task>\n";
        $prompt .= "Analyze the following {$reviewCount} reviews and their ratings to create:\n\n";
        $prompt .= "1. SUMMARY: A truthful summary that reflects the actual consensus from reviews\n";
        $prompt .= "   - FOLLOW THE SENTIMENT GUIDANCE provided in the coaster context\n";
        $prompt .= "   - Use the rating distribution percentages to determine if opinions are divided\n";
        $prompt .= "   - MUST contain between 3 and 5 sentences\n\n";
        $prompt .= "2. PROS: List the most frequently praised aspects (MAX 4 words each)\n";
        $prompt .= "   - Only include aspects mentioned positively by multiple reviewers\n";
        $prompt .= "   - For highly rated coasters: 3-5 pros\n";
        $prompt .= "   - For poorly rated coasters: 1-2 pros (or none if truly bad)\n\n";
        $prompt .= "3. CONS: List the most frequently criticized aspects (MAX 4 words each)\n";
        $prompt .= "   - Only include aspects mentioned negatively by multiple reviewers\n";
        $prompt .= "   - For highly rated coasters: 1-2 cons (or none if universally loved)\n";
        $prompt .= "   - For poorly rated coasters: 3-5 cons\n\n";
        $prompt .= "IMPORTANT GUIDELINES:\n";
        if ($outputLanguageInstruction) {
            $prompt .= "- {$outputLanguageInstruction}\n";
        }
        $prompt .= "- Never mention safety, legal, maintenance, construction or security issues\n";
        $prompt .= "- Be honest about the actual sentiment - don't force balance if reviews are overwhelmingly positive or negative\n";
        $prompt .= "- Empty pros or cons arrays are acceptable if not supported by review content\n";
        $prompt .= "</analysis_task>\n\n";

        // Include vocabulary guide for all languages (including English for consistency)
        $vocabularyGuideContent = $this->getVocabularyGuide($language);
        if ($vocabularyGuideContent) {
            $prompt .= "<vocabulary_guide>\n{$vocabularyGuideContent}\n</vocabulary_guide>\n\n";
        }

        // Coaster context section with enhanced formatting and rating distribution
        if ($coaster && $coaster->getStatus()) {
            $prompt .= "<coaster_context>\n";
            $prompt .= "Coaster: {$sanitizedName}\n";
            $prompt .= "Status: {$coaster->getStatus()->getName()}\n";

            if ($coaster->getAverageRating() && $coaster->getTotalRatings() > 0) {
                $ratingPercent = round(((float) $coaster->getAverageRating() / 10) * 100, 1);
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

        // Enhanced output format with examples
        $prompt .= "<output_format>\n";
        $prompt .= "Respond with valid JSON in this exact format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"summary\": \"Your analysis in {$languageName} reflecting the actual review consensus\",\n";
        $prompt .= "  \"pros\": [\"positive aspect 1\", \"positive aspect 2\"],\n";
        $prompt .= "  \"cons\": [\"concern 1\"]\n";
        $prompt .= "}\n";
        $prompt .= "\n";
        $prompt .= "Notes:\n";
        $prompt .= "- Pros and cons arrays can have 0-5 items each based on actual review content\n";
        $prompt .= "- Empty arrays [] are valid if no consistent themes emerge\n";
        $prompt .= "- Don't force artificial balance - reflect the true sentiment\n";
        $prompt .= "Ensure your response is valid JSON that can be parsed directly.\n";
        $prompt .= '</output_format>';

        return $prompt;
    }

    /** Parses AI response with security validation and data sanitization */
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
