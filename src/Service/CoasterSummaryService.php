<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CoasterSummaryService
{
    private const MAX_REVIEWS_FOR_ANALYSIS = 600;
    private const MIN_REVIEWS_REQUIRED = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RiddenCoasterRepository $riddenCoasterRepository,
        private BedrockService $bedrockService,
        private LoggerInterface $logger
    ) {
    }

    public function getCoasterReviews(Coaster $coaster): array
    {
        return $this->riddenCoasterRepository->getCoasterReviewsWithText($coaster, self::MAX_REVIEWS_FOR_ANALYSIS);
    }

    public function generateSummary(Coaster $coaster, ?string $modelKey = null, string $language = 'en'): ?CoasterSummary
    {
        $reviewsWithText = $this->getCoasterReviews($coaster);
        $reviewCount = \count($reviewsWithText);

        $this->logger->info('Processing coaster', ['coaster' => $coaster->getName(), 'reviews' => $reviewCount]);

        if ($reviewCount < self::MIN_REVIEWS_REQUIRED) {
            $this->logger->error('Insufficient reviews for analysis', [
                'coaster' => $coaster->getName(),
                'found' => $reviewCount,
                'required' => self::MIN_REVIEWS_REQUIRED,
            ]);
            return null;
        }

        $aiAnalysis = $this->analyzeReviews($reviewsWithText, $coaster->getName(), $modelKey);

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', ['coaster' => $coaster->getName()]);
            return null;
        }

        $summary = $this->findOrCreateSummary($coaster, $language);
        $summary->setSummary($aiAnalysis['summary']);
        $summary->setDynamicPros($aiAnalysis['pros']);
        $summary->setDynamicCons($aiAnalysis['cons']);
        $summary->setReviewsAnalyzed($reviewCount);
        $summary->setLanguage($language);

        $this->entityManager->persist($summary);
        $this->entityManager->flush();

        return $summary;
    }

    public function getInputStats(Coaster $coaster, ?string $modelKey = null): ?array
    {
        $reviewsWithText = $this->getCoasterReviews($coaster);
        $reviewCount = \count($reviewsWithText);

        if ($reviewCount < self::MIN_REVIEWS_REQUIRED) {
            return null;
        }

        $reviewTexts = array_map(fn ($review) => $review->getReview(), $reviewsWithText);
        $inputData = $this->buildPrompt($reviewTexts, $coaster->getName());
        $inputLength = \strlen($inputData);
        $wordCount = str_word_count($inputData);
        $estimatedTokens = (int) ($wordCount * 1.3);

        $model = $this->bedrockService->getModelConfig($modelKey ?? 'gpt-oss-120b');
        $estimatedCost = ($estimatedTokens / 1000) * $model['input_cost_per_1k'];

        return [
            'reviewCount' => $reviewCount,
            'inputLength' => $inputLength,
            'wordCount' => $wordCount,
            'estimatedTokens' => $estimatedTokens,
            'estimatedCost' => $estimatedCost,
            'model' => $model['id'],
        ];
    }

    public function shouldUpdateSummary(Coaster $coaster, string $language = 'en'): bool
    {
        $summary = $this->entityManager->getRepository(CoasterSummary::class)
            ->findOneBy(['coaster' => $coaster, 'language' => $language]);

        if (!$summary) {
            return true;
        }

        $currentReviewCount = $this->riddenCoasterRepository->countCoasterReviewsWithText($coaster);
        $analyzedCount = $summary->getReviewsAnalyzed();
        $reviewDiff = $currentReviewCount - $analyzedCount;
        $threshold = max(self::MIN_REVIEWS_REQUIRED, (int) ($analyzedCount * 0.2));

        return $reviewDiff >= $threshold || $summary->getUpdatedAt() < new \DateTime('-90 days');
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

        return $this->parseAiResponse($response['content']);
    }

    private function buildPrompt(array $reviewTexts, string $coasterName): string
    {
        $combinedReviews = implode("\n\n---\n\n", $reviewTexts);
        $reviewCount = count($reviewTexts);

        return "Analyze these {$reviewCount} multilingual roller coaster reviews for {$coasterName}.

Provide:
1. A concise summary (2-4 sentences) reflecting the actual reviewer consensus
2. Positive aspects: ONLY list aspects that are genuinely mentioned by multiple reviewers (could be 0, 1, 2, 3, 4, or 5 items)
3. Negative aspects: ONLY list aspects that are genuinely mentioned by multiple reviewers (could be 0, 1, 2, 3, 4, or 5 items)

CRITICAL INSTRUCTIONS:
- DO NOT invent pros/cons to reach a certain number
- If most reviews are negative, you may have 0-1 pros and 3-5 cons
- If most reviews are positive, you may have 3-5 pros and 0-1 cons
- Only include what reviewers actually mention repeatedly
- Never mention legal, safety, or security aspects
- Each aspect should be 2-5 words

Reviews:
{$combinedReviews}

Examples of valid responses:
- Mostly negative: {\"pros\": [], \"cons\": [\"rough ride\", \"long queues\"]}
- Mostly positive: {\"pros\": [\"smooth ride\", \"great theming\"], \"cons\": []}
- Mixed: {\"pros\": [\"intense experience\"], \"cons\": [\"too short\", \"uncomfortable seats\"]}

Format as JSON:
{
  \"summary\": \"Your honest summary\",
  \"pros\": [\"only genuine positives\"],
  \"cons\": [\"only genuine negatives\"]
}";
    }

    private function parseAiResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['summary'])) {
                return [
                    'summary' => $json['summary'],
                    'pros' => $json['pros'] ?? [],
                    'cons' => $json['cons'] ?? [],
                ];
            }
        }

        return ['summary' => '', 'pros' => [], 'cons' => []];
    }
}