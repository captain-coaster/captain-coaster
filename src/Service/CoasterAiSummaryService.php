<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\CoasterAiSummary;
use App\Repository\RiddenCoasterRepository;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CoasterAiSummaryService
{
    private const MAX_REVIEWS_FOR_ANALYSIS = 600;
    private const MIN_REVIEWS_REQUIRED = 20;

    private const MODELS = [
        'claude-haiku-3.5' => [
            'id' => 'us.anthropic.claude-3-5-haiku-20241022-v1:0',
            'input_cost_per_1k' => 0.0008,
            'output_cost_per_1k' => 0.004,
            'type' => 'anthropic',
        ],
        'gpt-oss-120b' => [
            'id' => 'openai.gpt-oss-120b-1:0',
            'input_cost_per_1k' => 0.00015,
            'output_cost_per_1k' => 0.0006,
            'type' => 'openai',
        ],
    ];

    private const DEFAULT_MODEL = 'gpt-oss-120b';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RiddenCoasterRepository $riddenCoasterRepository,
        private BedrockRuntimeClient $bedrockClient,
        private LoggerInterface $logger,
        private string $modelKey = self::DEFAULT_MODEL
    ) {
    }

    public function getCoasterReviews(Coaster $coaster): array
    {
        return $this->riddenCoasterRepository->getCoasterReviewsWithText($coaster, self::MAX_REVIEWS_FOR_ANALYSIS);
    }

    public function generateSummary(Coaster $coaster, ?string $modelKey = null, string $language = 'en'): ?CoasterAiSummary
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

        $model = self::MODELS[$modelKey ?? $this->modelKey];
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

    public function getSummary(Coaster $coaster, string $language = 'en'): ?CoasterAiSummary
    {
        return $this->entityManager->getRepository(CoasterAiSummary::class)
            ->findOneBy(['coaster' => $coaster, 'language' => $language]);
    }

    public function shouldUpdateSummary(Coaster $coaster, string $language = 'en'): bool
    {
        $summary = $this->getSummary($coaster, $language);

        if (!$summary) {
            return true;
        }

        $currentReviewCount = $this->riddenCoasterRepository->countCoasterReviewsWithText($coaster);
        $analyzedCount = $summary->getReviewsAnalyzed();
        $reviewDiff = $currentReviewCount - $analyzedCount;
        $threshold = max(self::MIN_REVIEWS_REQUIRED, (int) ($analyzedCount * 0.2));

        return $reviewDiff >= $threshold || $summary->getUpdatedAt() < new \DateTime('-90 days');
    }

    private function findOrCreateSummary(Coaster $coaster, string $language = 'en'): CoasterAiSummary
    {
        $summary = $this->getSummary($coaster, $language);

        if (!$summary) {
            $summary = new CoasterAiSummary();
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

        $model = self::MODELS[$modelKey ?? $this->modelKey];
        $prompt = $this->buildPrompt($reviewTexts, $coasterName);

        try {
            $this->logger->info('Calling AWS Bedrock API', ['coaster' => $coasterName, 'model' => $model['id']]);

            $requestBody = $this->buildRequestBody($model, $prompt);

            $response = $this->bedrockClient->invokeModel([
                'modelId' => $model['id'],
                'contentType' => 'application/json',
                'body' => json_encode($requestBody),
            ]);

            $result = json_decode($response['body']->getContents(), true);
            $metadata = $response['@metadata'];

            $latencyMs = $metadata['headers']['x-amzn-bedrock-latency'] ?? null;
            $inputTokens = $metadata['headers']['x-amzn-bedrock-input-token-count'] ?? 0;
            $outputTokens = $metadata['headers']['x-amzn-bedrock-output-token-count'] ?? 0;

            $inputCost = ($inputTokens / 1000) * $model['input_cost_per_1k'];
            $outputCost = ($outputTokens / 1000) * $model['output_cost_per_1k'];
            $totalCost = $inputCost + $outputCost;

            $logData = [
                'coaster' => $coasterName,
                'model' => $model['id'],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round($totalCost, 6),
            ];

            if (null !== $latencyMs) {
                $logData['latency_ms'] = $latencyMs;
            }

            $this->logger->info('Bedrock API call completed', $logData);

            return $this->parseAiResponse($this->extractResponseText($result, $model['type']));
        } catch (AwsException $e) {
            $this->logger->error('AWS Bedrock API error', ['coaster' => $coasterName, 'error' => $e->getMessage()]);

            return ['summary' => '', 'pros' => [], 'cons' => []];
        }
    }

    private function buildPrompt(array $reviewTexts, string $coasterName): string
    {
        $combinedReviews = implode("\n\n---\n\n", $reviewTexts);
        $reviewCount = \count($reviewTexts);

        return "You are Captain Coaster, an expert to analyze roller coaster reviews and give future riders the best possible advice on coasters. Analyze these {$reviewCount} multilingual roller coaster reviews for {$coasterName}.

Provide:
1. A concise summary (3-4 sentences) reflecting the actual reviewer consensus
2. Most frequently mentioned positive aspects (pros) in English (maximum 5 and 2-5 words each)
2. Most frequently mentioned negative aspects (cons) in English (maximum 5 and 2-5 words each)

Reviews:
{$combinedReviews}

CRITICAL INSTRUCTIONS:
- If most reviews are negative, you may have 0-1 pros and 3-5 cons
- If most reviews are positive, you may have 3-5 pros and 0-1 cons
- Only include what reviewers actually mention repeatedly
- Respect 3 sentences minimum for the summary
- Feel free to add a slight touch of humour if appropriate
- Never mention legal, safety, maintenance, or security aspects

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

    public function getAvailableModels(): array
    {
        return array_keys(self::MODELS);
    }

    public function getModelConfig(string $modelKey): ?array
    {
        return self::MODELS[$modelKey] ?? null;
    }

    private function buildRequestBody(array $model, string $prompt): array
    {
        return match ($model['type']) {
            'anthropic' => [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => 1000,
                'temperature' => 0.6,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ],
            'openai' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 1000,
                'temperature' => 0.6,
            ],
            default => throw new \InvalidArgumentException("Unsupported model type: {$model['type']}")
        };
    }

    private function extractResponseText(array $result, string $modelType): string
    {
        return match ($modelType) {
            'anthropic' => $result['content'][0]['text'] ?? '',
            'openai' => $result['choices'][0]['message']['content'] ?? '',
            default => ''
        };
    }
}
