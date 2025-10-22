<?php

declare(strict_types=1);

namespace App\Service;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;

/**
 * Service for AWS Bedrock AI model interactions.
 *
 * Handles communication with AWS Bedrock runtime, including model invocation,
 * cost calculation, and response parsing. Supports multiple AI models with
 * different request/response formats.
 */
class BedrockService
{
    /** Available AI models with their configurations */
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
        private BedrockRuntimeClient $bedrockClient,
        private LoggerInterface $logger,
        private string $modelKey = self::DEFAULT_MODEL
    ) {
    }

    public function invokeModel(string $prompt, ?string $modelKey = null): array
    {
        $model = self::MODELS[$modelKey ?? $this->modelKey];

        try {
            $this->logger->info('Calling AWS Bedrock API', ['model' => $model['id']]);

            $requestBody = $this->buildRequestBody($model, $prompt);

            $response = $this->bedrockClient->invokeModel([
                'modelId' => $model['id'],
                'contentType' => 'application/json',
                'body' => json_encode($requestBody, \JSON_THROW_ON_ERROR),
            ]);

            $responseBody = $response['body']->getContents();
            if (empty($responseBody)) {
                throw new \RuntimeException('Empty response from Bedrock API');
            }

            $result = json_decode($responseBody, true, 10, \JSON_THROW_ON_ERROR);
            $metadata = $response['@metadata'];

            $latencyMs = $metadata['headers']['x-amzn-bedrock-invocation-latency'] ?? null;
            $inputTokens = $metadata['headers']['x-amzn-bedrock-input-token-count'] ?? 0;
            $outputTokens = $metadata['headers']['x-amzn-bedrock-output-token-count'] ?? 0;

            $inputCost = ($inputTokens / 1000) * $model['input_cost_per_1k'];
            $outputCost = ($outputTokens / 1000) * $model['output_cost_per_1k'];
            $totalCost = $inputCost + $outputCost;

            $logData = [
                'model' => $model['id'],
                'latency_ms' => $latencyMs,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round($totalCost, 6),
            ];

            $this->logger->info('Bedrock API call completed', $logData);

            return [
                'success' => true,
                'content' => $this->extractResponseText($result, $model['type']),
                'metadata' => $logData,
            ];
        } catch (AwsException $e) {
            $this->logger->error('AWS Bedrock API error', [
                'error' => $e->getMessage(),
                'model' => $model['id'],
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getAwsErrorCode(),
            ];
        } catch (\JsonException $e) {
            $this->logger->error('JSON encoding/decoding error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Invalid JSON response from AI model',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in Bedrock service', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Unexpected error occurred',
            ];
        }
    }

    /**
     * Builds request body based on model type
     * Different AI providers require different request formats.
     */
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

    /**
     * Extracts text content from AI model response
     * Different models return responses in different formats.
     */
    private function extractResponseText(array $result, string $modelType): string
    {
        return match ($modelType) {
            'anthropic' => $result['content'][0]['text'] ?? '',
            'openai' => $result['choices'][0]['message']['content'] ?? '',
            default => ''
        };
    }
}
