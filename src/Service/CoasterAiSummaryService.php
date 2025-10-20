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
    private const MAX_REVIEWS_FOR_ANALYSIS = 200;
    private const MIN_REVIEWS_REQUIRED = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RiddenCoasterRepository $riddenCoasterRepository,
        private BedrockRuntimeClient $bedrockClient,
        private LoggerInterface $logger
    ) {
    }

    public function getCoasterReviews(Coaster $coaster): array
    {
        return $this->riddenCoasterRepository->getCoasterReviewsWithText($coaster, self::MAX_REVIEWS_FOR_ANALYSIS);
    }

    public function generateSummary(Coaster $coaster): ?CoasterAiSummary
    {
        $reviewsWithText = $this->getCoasterReviews($coaster);
        $this->logger->info('Processing coaster', ['coaster' => $coaster->getName(), 'reviews' => \count($reviewsWithText)]);

        if (\count($reviewsWithText) < self::MIN_REVIEWS_REQUIRED) {
            $this->logger->error('Insufficient reviews for analysis', ['coaster' => $coaster->getName(), 'found' => \count($reviewsWithText), 'required' => self::MIN_REVIEWS_REQUIRED]);

            return null;
        }

        $aiAnalysis = $this->analyzeReviews($reviewsWithText, $coaster->getName());

        if (empty($aiAnalysis['summary'])) {
            $this->logger->error('AI analysis returned empty summary', ['coaster' => $coaster->getName()]);

            return null;
        }

        $summary = $this->findOrCreateSummary($coaster);
        $summary->setSummary($aiAnalysis['summary']);
        $summary->setDynamicPros($aiAnalysis['pros']);
        $summary->setDynamicCons($aiAnalysis['cons']);
        $summary->setReviewsAnalyzed(\count($reviewsWithText));

        $this->entityManager->persist($summary);
        $this->entityManager->flush();

        return $summary;
    }

    public function getInputStats(Coaster $coaster): ?array
    {
        $reviewsWithText = $this->getCoasterReviews($coaster);

        if (\count($reviewsWithText) < self::MIN_REVIEWS_REQUIRED) {
            return null;
        }

        $reviewTexts = array_map(fn ($review) => $review->getReview(), $reviewsWithText);
        $inputData = $this->buildPrompt($reviewTexts, $coaster->getName());
        $inputLength = \strlen($inputData);
        $wordCount = str_word_count($inputData);
        $estimatedTokens = (int) ($wordCount * 1.3);

        return [
            'reviewCount' => \count($reviewsWithText),
            'inputLength' => $inputLength,
            'wordCount' => $wordCount,
            'estimatedTokens' => $estimatedTokens,
        ];
    }

    public function getSummary(Coaster $coaster): ?CoasterAiSummary
    {
        return $this->entityManager->getRepository(CoasterAiSummary::class)
            ->findOneBy(['coaster' => $coaster]);
    }

    public function shouldUpdateSummary(Coaster $coaster): bool
    {
        $summary = $this->getSummary($coaster);

        if (!$summary) {
            return true;
        }

        $currentReviewCount = $this->riddenCoasterRepository->countCoasterReviewsWithText($coaster);
        $analyzedCount = $summary->getReviewsAnalyzed();

        // Update if 20% more reviews or 90 days old
        return ($currentReviewCount - $analyzedCount) >= max(self::MIN_REVIEWS_REQUIRED, $analyzedCount * 0.2)
               || $summary->getUpdatedAt() < new \DateTime('-90 days');
    }

    private function findOrCreateSummary(Coaster $coaster): CoasterAiSummary
    {
        $summary = $this->getSummary($coaster);

        if (!$summary) {
            $summary = new CoasterAiSummary();
            $summary->setCoaster($coaster);
        }

        return $summary;
    }

    private function analyzeReviews(array $reviews, string $coasterName): array
    {
        $reviewTexts = array_map(fn ($review) => $review->getReview(), $reviews);

        if (empty($reviewTexts)) {
            return ['summary' => '', 'pros' => [], 'cons' => []];
        }

        $prompt = $this->buildPrompt($reviewTexts, $coasterName);

        try {
            $this->logger->info('Calling AWS Bedrock API', ['coaster' => $coasterName]);
            $startTime = microtime(true);

            $response = $this->bedrockClient->invokeModel([
                'modelId' => 'us.anthropic.claude-3-5-haiku-20241022-v1:0',
                'contentType' => 'application/json',
                'body' => json_encode([
                    'anthropic_version' => 'bedrock-2023-05-31',
                    'max_tokens' => 1000,
                    'temperature' => 0.6,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]),
            ]);

            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $result = json_decode($response['body']->getContents(), true);

            $inputTokens = $response['@metadata']['headers']['x-amzn-bedrock-input-token-count'] ?? 0;
            $outputTokens = $response['@metadata']['headers']['x-amzn-bedrock-output-token-count'] ?? 0;
            $totalTokens = $inputTokens + $outputTokens;

            $inputCost = ($inputTokens / 1000) * 0.0008;
            $outputCost = ($outputTokens / 1000) * 0.004;
            $totalCost = $inputCost + $outputCost;

            $this->logger->info('Bedrock API call completed', [
                'coaster' => $coasterName,
                'latency_ms' => $latency,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'cost_usd' => $totalCost,
            ]);

            return $this->parseAiResponse($result['content'][0]['text']);
        } catch (AwsException $e) {
            $this->logger->error('AWS Bedrock API error', ['coaster' => $coasterName, 'error' => $e->getMessage()]);

            return ['summary' => '', 'pros' => [], 'cons' => []];
        }
    }

    private function buildPrompt(array $reviewTexts, string $coasterName): string
    {
        $combinedReviews = implode("\n\n---\n\n", $reviewTexts);

        return "Analyze these multilingual roller coaster reviews for {$coasterName} and provide:

1. A concise 2-4 sentence summary in English highlighting the main consensus
2. 2-5 of the most mentioned positive aspects (pros) in English (2-5 words each)
3. 2-5 of the most mentioned negative aspects (cons) in English (2-5 words each)

Reviews:
{$combinedReviews}

Format your response as JSON:
{
  \"summary\": \"Your summary here\",
  \"pros\": [\"pro1\", \"pro2\", \"pro3\"],
  \"cons\": [\"con1\", \"con2\"]
}
Never talk about legal or safety aspects.";
    }

    private function parseAiResponse(string $response): array
    {
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return [
                    'summary' => $json['summary'] ?? '',
                    'pros' => $json['pros'] ?? [],
                    'cons' => $json['cons'] ?? [],
                ];
            }
        }

        return ['summary' => '', 'pros' => [], 'cons' => []];
    }
}
