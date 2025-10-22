<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating and managing AI-powered coaster summaries.
 *
 * This service analyzes coaster reviews using AWS Bedrock AI models to generate
 * summaries with pros/cons lists. It handles review collection, AI analysis,
 * and summary persistence with language support.
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
        private BedrockService $bedrockService,
        private LoggerInterface $logger
    ) {
    }

    /** Gets count of reviews with text content for a coaster */
    public function getReviewCount(Coaster $coaster): int
    {
        return $this->riddenCoasterRepository->countCoasterReviewsWithText($coaster);
    }

    /** Generates an AI summary for a coaster based on its reviews */
    public function generateSummary(Coaster $coaster, ?string $modelKey = null, string $language = 'en'): array
    {
        $currentReviewCount = $this->getReviewCount($coaster);

        if ($currentReviewCount < self::MIN_REVIEWS_REQUIRED) {
            $this->logger->info('Not enough reviews to generate summary', ['coaster' => $coaster->getName(), 'reviews' => $currentReviewCount]);

            return ['summary' => null, 'metadata' => null];
        }

        $reviewsWithText = $this->riddenCoasterRepository->getCoasterReviewsWithText($coaster, self::MAX_REVIEWS_FOR_ANALYSIS);
        $reviewCount = \count($reviewsWithText);

        $this->logger->info('Processing coaster', ['coaster' => $coaster->getName(), 'reviews' => $reviewCount]);

        $aiAnalysis = $this->analyzeReviews($reviewsWithText, $coaster->getName(), $modelKey);

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', ['coaster' => $coaster->getName()]);

            return ['summary' => null, 'metadata' => $aiAnalysis['metadata'] ?? null];
        }

        $summary = $this->findOrCreateSummary($coaster, $language);
        $summary->setSummary($aiAnalysis['summary']);
        $summary->setDynamicPros($aiAnalysis['pros']);
        $summary->setDynamicCons($aiAnalysis['cons']);
        $summary->setReviewsAnalyzed($reviewCount);
        $summary->setLanguage($language);

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

    private function analyzeReviews(array $reviews, string $coasterName, ?string $modelKey = null): array
    {
        $reviewTexts = array_map(fn ($review) => $review->getReview(), $reviews);

        if (empty($reviewTexts)) {
            return ['summary' => '', 'pros' => [], 'cons' => []];
        }

        $prompt = $this->buildPrompt($reviewTexts, $coasterName);
        $response = $this->bedrockService->invokeModel($prompt, $modelKey);

        if (!$response['success']) {
            $this->logger->error('Bedrock service error', ['coaster' => $coasterName, 'error' => $response['error']]);

            return ['summary' => '', 'pros' => [], 'cons' => []];
        }

        $this->logger->info('Bedrock API call completed', array_merge(['coaster' => $coasterName], $response['metadata']));

        return array_merge(
            $this->parseAiResponse($response['content']),
            ['metadata' => $response['metadata']]
        );
    }

    /** Builds the AI prompt for review analysis with security sanitization */
    private function buildPrompt(array $reviewTexts, string $coasterName): string
    {
        // Sanitize coaster name to prevent prompt injection
        $sanitizedName = preg_replace('/[^\w\s-]/', '', $coasterName);
        $combinedReviews = implode("\n\n---\n\n", $reviewTexts);
        $reviewCount = \count($reviewTexts);

        return "You are a roller coaster expert who gives future riders the best possible advice. Analyze these {$reviewCount} multilingual roller coaster reviews for {$sanitizedName}.

Provide:
1. A concise summary (3-4 sentences) reflecting the actual reviewer consensus
2. Most frequently mentioned positive aspects (pros) in English (maximum 5 and 2-5 words each)
3. Most frequently mentioned negative aspects (cons) in English (maximum 5 and 2-5 words each)

CRITICAL INSTRUCTIONS:
- If most reviews are negative, you may have 0-2 pros and 3-5 cons
- If most reviews are positive, you may have 3-5 pros and 0-2 cons
- Only include what reviewers actually mention repeatedly
- Respect 3 sentences minimum for the summary
- Never mention legal, safety, maintenance, or security aspects

Reviews:
{$combinedReviews}

Format as JSON:
{
  \"summary\": \"Your honest summary\",
  \"pros\": [\"only genuine positives\"],
  \"cons\": [\"only genuine negatives\"]
}";
    }

    /** Parses AI response with security validation and data sanitization */
    private function parseAiResponse(string $response): array
    {
        try {
            if (preg_match('/\{.*\}/s', $response, $matches)) {
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
            $this->logger->warning('Failed to parse AI response JSON', ['error' => $e->getMessage()]);
        }

        return ['summary' => '', 'pros' => [], 'cons' => []];
    }
}
