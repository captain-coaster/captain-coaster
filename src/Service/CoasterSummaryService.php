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
 * Cascade Deletion Behavior:
 * - When an English summary is deleted, all translations (fr, es, de) are automatically deleted
 * - When any summary is deleted, all associated feedback records are automatically deleted
 * - This is handled by CoasterSummaryListener and entity cascade configuration
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

        $aiAnalysis = $this->analyzeReviews($reviewsWithText, $coaster->getName(), $modelKey);

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', ['coaster' => $coaster->getName()]);

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

            return ['summary' => '', 'pros' => [], 'cons' => [], 'metadata' => $response['metadata'] ?? null];
        }

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

        // Include English vocabulary guide if available
        $vocabularySection = '';
        $vocabularyGuide = $this->vocabularyGuideRepository->findByLanguage('en');
        if ($vocabularyGuide) {
            $guide = $vocabularyGuide->getContent();
            $vocabularySection = "\n{$guide}\n\n---\n\n";
        }

        return "You are a roller coaster expert who gives future riders the best possible advice. Analyze these {$reviewCount} multilingual roller coaster reviews for {$sanitizedName}.
{$vocabularySection}
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
            $this->logger->warning('Failed to parse AI response JSON', ['error' => $e->getMessage()]);
        }

        return ['summary' => '', 'pros' => [], 'cons' => []];
    }

    /**
     * Translates an English summary to a target language using terminology guide.
     *
     * @param CoasterSummary $sourceSummary  The English summary to translate
     * @param string         $targetLanguage Target language code (fr, es, de)
     * @param string|null    $modelKey       Optional Bedrock model key
     *
     * @return array Result with 'summary' (CoasterSummary|null), 'metadata', and optional 'reason'
     */
    public function translateSummary(
        CoasterSummary $sourceSummary,
        string $targetLanguage,
        ?string $modelKey = null
    ): array {
        // Validate source summary is in English
        if ('en' !== $sourceSummary->getLanguage()) {
            $this->logger->error('Cannot translate non-English summary', [
                'source_language' => $sourceSummary->getLanguage(),
                'target_language' => $targetLanguage,
            ]);

            return [
                'summary' => null,
                'metadata' => null,
                'reason' => 'source_not_english',
            ];
        }

        // Get vocabulary guide for target language (auto-generated content only)
        $vocabularyGuideEntity = $this->vocabularyGuideRepository->findByLanguage($targetLanguage);
        if (!$vocabularyGuideEntity) {
            $this->logger->warning('No vocabulary guide for target language', [
                'target_language' => $targetLanguage,
            ]);

            return [
                'summary' => null,
                'metadata' => null,
                'reason' => 'missing_vocabulary_guide',
            ];
        }

        $vocabularyGuide = $vocabularyGuideEntity->getContent();

        // Get example reviews in target language for this coaster
        $coaster = $sourceSummary->getCoaster();
        $riddenCoasters = $this->riddenCoasterRepository->getCoasterReviewsWithTextByLanguage($coaster, $targetLanguage, 10);
        $exampleReviews = array_map(fn ($rc) => $rc->getReview(), $riddenCoasters);

        // Build translation prompt with vocabulary guide and examples
        $prompt = $this->buildTranslationPrompt(
            $sourceSummary->getSummary(),
            $sourceSummary->getDynamicPros(),
            $sourceSummary->getDynamicCons(),
            $targetLanguage,
            $vocabularyGuide,
            $exampleReviews,
            $coaster->getName()
        );

        // Invoke AI model for translation (use Claude for better language understanding)
        $response = $this->bedrockService->invokeModel($prompt, 'claude-haiku-4.5', 2000, 0.5);

        if (!$response['success']) {
            $this->logger->error('Translation failed', [
                'coaster' => $sourceSummary->getCoaster()->getName(),
                'target_language' => $targetLanguage,
                'error' => $response['error'] ?? 'Unknown error',
            ]);

            return [
                'summary' => null,
                'metadata' => $response['metadata'] ?? null,
                'reason' => 'ai_error',
            ];
        }

        // Parse translation response
        $translation = $this->parseAiResponse($response['content']);

        if (empty($translation['summary'])) {
            $this->logger->error('AI translation returned empty summary', [
                'coaster' => $sourceSummary->getCoaster()->getName(),
                'target_language' => $targetLanguage,
                'raw_response' => substr($response['content'], 0, 500), // Log first 500 chars
            ]);

            return [
                'summary' => null,
                'metadata' => $response['metadata'] ?? null,
                'reason' => 'empty_translation',
            ];
        }

        // Create or update translation summary
        $translatedSummary = $this->findOrCreateSummary($sourceSummary->getCoaster(), $targetLanguage);
        $translatedSummary->setSummary($translation['summary']);
        $translatedSummary->setDynamicPros($translation['pros']);
        $translatedSummary->setDynamicCons($translation['cons']);
        $translatedSummary->setReviewsAnalyzed($sourceSummary->getReviewsAnalyzed());
        $translatedSummary->setLanguage($targetLanguage);

        // Reset votes when translation is regenerated
        $translatedSummary->setPositiveVotes(0);
        $translatedSummary->setNegativeVotes(0);
        $translatedSummary->setFeedbackRatio(0.0);

        // Clear existing feedback records
        $this->clearSummaryFeedback($translatedSummary);

        $this->entityManager->persist($translatedSummary);
        $this->entityManager->flush();

        return [
            'summary' => $translatedSummary,
            'metadata' => $response['metadata'],
        ];
    }

    /**
     * Determines if a translation should be updated.
     * Uses same logic as shouldUpdateSummary but for translations.
     *
     * @param Coaster $coaster  The coaster to check
     * @param string  $language The target language
     *
     * @return bool True if translation needs update
     */
    public function shouldUpdateTranslation(Coaster $coaster, string $language): bool
    {
        // For translations, we use the same logic as shouldUpdateSummary
        return $this->shouldUpdateSummary($coaster, $language);
    }

    /** Builds the AI prompt for translating a summary to target language. */
    private function buildTranslationPrompt(
        string $summaryText,
        array $pros,
        array $cons,
        string $targetLanguage,
        string $vocabularyGuide,
        array $exampleReviews,
        string $coasterName
    ): string {
        $languageNames = [
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
        ];

        $languageName = $languageNames[$targetLanguage] ?? $targetLanguage;

        // Format pros and cons as lists
        $prosText = implode("\n", array_map(fn ($pro) => "- {$pro}", $pros));
        $consText = implode("\n", array_map(fn ($con) => "- {$con}", $cons));

        // Format example reviews
        $examplesText = '';
        if (!empty($exampleReviews)) {
            $examplesText = implode("\n\n", array_map(
                fn ($review, $index) => \sprintf('Example %d: %s', $index + 1, $review),
                $exampleReviews,
                array_keys($exampleReviews)
            ));
        } else {
            $examplesText = "(No example reviews available for this coaster in {$languageName})";
        }

        return "You are a professional translator specializing in roller coaster content for enthusiast communities.

Your task is to translate an English roller coaster summary into natural, fluid {$languageName} that sounds like it was originally written by a native speaker enthusiast.

These are authentic reviews of {$coasterName} written by native {$languageName} speakers. Study their vocabulary, expressions, and writing style:

<example_reviews>
{$examplesText}
</example_reviews>

<summary>
{$summaryText}
</summary>

<pros>
{$prosText}
</pros>

<cons>
{$consText}
</cons>

<vocabulary_guide>
{$vocabularyGuide}
</vocabulary_guide>

<translation_rules>
1. DO NOT translate word-for-word from English - adapt to natural {$languageName}
2. DO NOT EXAGGERATE - keep the same intensity level as the English source
3. USE the vocabulary guide strictly for technical terms, grammar rules, and domain-specific expressions
4. Keep the TONE PROFESSIONAL, true to the English source, AVOID slang
5. Translate STRICTLY pros and cons (maximum 3 words each) while maintaining natural phrasing
</translation_rules>

<output_format>
Return valid JSON only:
{
  \"summary\": \"translated summary in natural {$languageName}\",
  \"pros\": [\"translated pro 1\", \"translated pro 2\", ...],
  \"cons\": [\"translated con 1\", \"translated con 2\", ...]
}
</output_format>";
    }
}
