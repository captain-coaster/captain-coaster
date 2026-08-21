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
        'gpt-oss-120b' => [
            'id' => 'openai.gpt-oss-120b-1:0',
            'input_cost_per_1k' => 0.00015,
            'output_cost_per_1k' => 0.0006,
            'reasoning_effort' => 'low',
        ],
        // Rates below are the "Global CRIS" row (our modelId uses the global. prefix) from
        // the model card's own pricing table, not the prompt-caching guide (that page only
        // documents caching for this model via the Responses API - Converse caches it too,
        // confirmed live, but AWS doesn't document why; this pricing table isn't scoped to
        // a specific API, so it's the best available source for what a Converse-triggered
        // cache read/write actually costs): https://docs.aws.amazon.com/bedrock/latest/userguide/model-card-openai-gpt-56-luna.html
        'gpt-5.6-luna' => [
            'id' => 'global.openai.gpt-5.6-luna',
            'input_cost_per_1k' => 0.0002,
            'output_cost_per_1k' => 0.0012,
            'cache_read_cost_per_1k' => 0.00002,
            'cache_write_cost_per_1k' => 0.00025,
            // Prompts whose total input (input + cache read + cache write) tokens exceed this
            // move from the "Short Context Window (272K)" price row to "Long Context Window
            // (1M)" - not a flat multiplier: input/cache rates double, output only rises 1.5x.
            'long_context_threshold_tokens' => 272_000,
            'long_context_input_cost_per_1k' => 0.0004,
            'long_context_output_cost_per_1k' => 0.0018,
            'long_context_cache_read_cost_per_1k' => 0.00004,
            'long_context_cache_write_cost_per_1k' => 0.0005,
            'supports_temperature' => false,
        ],
    ];

    public const DEFAULT_MODEL = 'gpt-5.6-luna';

    public function __construct(
        private BedrockRuntimeClient $bedrockClient,
        private LoggerInterface $logger,
        private string $modelKey = self::DEFAULT_MODEL
    ) {
    }

    /** @return array{success: bool, content?: string, error?: string, error_code?: string|null, metadata: array<string, mixed>} */
    public function invokeModel(string $prompt, ?string $modelKey = null, int $maxTokens = 1000, float $temperature = 0.6): array
    {
        $resolvedModelKey = $modelKey ?? $this->modelKey;

        if (!isset(self::MODELS[$resolvedModelKey])) {
            $this->logger->error('Unknown Bedrock model key requested', [
                'model_key' => $resolvedModelKey,
                'available_models' => array_keys(self::MODELS),
            ]);

            return [
                'success' => false,
                'error' => \sprintf('Unknown model "%s". Available models: %s', $resolvedModelKey, implode(', ', array_keys(self::MODELS))),
                'metadata' => ['model_key' => $resolvedModelKey],
            ];
        }

        $model = self::MODELS[$resolvedModelKey];

        try {
            $requestBody = $this->buildConverseRequest($prompt, $maxTokens, $temperature, $model['reasoning_effort'] ?? null, $model['supports_temperature'] ?? true);

            $response = $this->bedrockClient->converse($requestBody + ['modelId' => $model['id']]);

            $result = $response->toArray();
            $stopReason = $result['stopReason'] ?? null;

            // latencyMs lives under metrics, not a response header. Bedrock applies prompt
            // caching automatically for cache-capable models with no cachePoint required, so
            // cacheReadInputTokens/cacheWriteInputTokens need their own rates below - otherwise
            // a cache hit reports as if almost no input tokens were used at all.
            $usage = $result['usage'] ?? [];
            $inputTokens = $usage['inputTokens'] ?? 0;
            $outputTokens = $usage['outputTokens'] ?? 0;
            $cacheReadTokens = $usage['cacheReadInputTokens'] ?? 0;
            $cacheWriteTokens = $usage['cacheWriteInputTokens'] ?? 0;

            $latencyMs = $result['metrics']['latencyMs'] ?? null;

            $totalInputTokens = $inputTokens + $cacheReadTokens + $cacheWriteTokens;
            $isLongContext = isset($model['long_context_threshold_tokens']) && $totalInputTokens > $model['long_context_threshold_tokens'];

            $inputRate = $isLongContext ? $model['long_context_input_cost_per_1k'] : $model['input_cost_per_1k'];
            $outputRate = $isLongContext ? $model['long_context_output_cost_per_1k'] : $model['output_cost_per_1k'];
            $cacheReadRate = ($isLongContext ? ($model['long_context_cache_read_cost_per_1k'] ?? null) : ($model['cache_read_cost_per_1k'] ?? null)) ?? $inputRate;
            $cacheWriteRate = ($isLongContext ? ($model['long_context_cache_write_cost_per_1k'] ?? null) : ($model['cache_write_cost_per_1k'] ?? null)) ?? $inputRate;

            $inputCost = ($inputTokens / 1000) * $inputRate;
            $outputCost = ($outputTokens / 1000) * $outputRate;
            $cacheReadCost = ($cacheReadTokens / 1000) * $cacheReadRate;
            $cacheWriteCost = ($cacheWriteTokens / 1000) * $cacheWriteRate;
            $totalCost = $inputCost + $outputCost + $cacheReadCost + $cacheWriteCost;

            $metadata = [
                'model' => $model['id'],
                'latency_ms' => $latencyMs,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cache_read_tokens' => $cacheReadTokens,
                'cache_write_tokens' => $cacheWriteTokens,
                'cost_usd' => round($totalCost, 6),
                'stop_reason' => $stopReason,
            ];

            // Log successful requests for monitoring
            $this->logger->info('Bedrock API request successful', [
                'model' => $model['id'],
                'model_key' => $modelKey ?? $this->modelKey,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cache_read_tokens' => $cacheReadTokens,
                'cache_write_tokens' => $cacheWriteTokens,
                'cost_usd' => round($totalCost, 6),
                'latency_ms' => $latencyMs,
                'stop_reason' => $stopReason,
                'prompt_length' => \strlen($prompt),
                'response_length' => \strlen($this->parseConverseResponse($result)),
            ]);

            $content = $this->parseConverseResponse($result);

            // Check if content is empty and log warning
            if (empty($content)) {
                $this->logger->warning('Bedrock returned empty content', [
                    'model' => $model['id'],
                    'stop_reason' => $stopReason,
                    'result_structure' => json_encode($result, \JSON_PRETTY_PRINT),
                ]);
            }

            return [
                'success' => true,
                'content' => $content,
                'metadata' => $metadata,
            ];
        } catch (AwsException $e) {
            // Enhanced logging with detailed AWS error information
            $logContext = [
                'error' => $e->getMessage(),
                'model' => $model['id'],
                'model_key' => $modelKey ?? $this->modelKey,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_type' => $e->getAwsErrorType(),
                'request_id' => $e->getAwsRequestId(),
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'prompt_length' => \strlen($prompt),
                'http_status_code' => $e->getStatusCode(),
                'region' => $this->bedrockClient->getRegion(),
            ];

            // Add AWS response details if available
            if ($e->getResponse()) {
                $response = $e->getResponse();
                $logContext['response_status'] = $response->getStatusCode();
                $logContext['response_reason'] = $response->getReasonPhrase();

                // Log response headers (excluding sensitive data)
                $headers = $response->getHeaders();
                $safeHeaders = [];
                foreach ($headers as $name => $values) {
                    if (!\in_array(strtolower($name), ['authorization', 'x-amz-security-token'])) {
                        $safeHeaders[$name] = $values;
                    }
                }
                $logContext['response_headers'] = $safeHeaders;

                // Log response body if it's not too large
                $body = $response->getBody()->getContents();
                if (\strlen($body) < 2000) {
                    $logContext['response_body'] = $body;
                } else {
                    $logContext['response_body_length'] = \strlen($body);
                    $logContext['response_body_preview'] = substr($body, 0, 500).'...';
                }
            }

            // Add request details for debugging
            $logContext['request_body'] = json_encode($requestBody, \JSON_PRETTY_PRINT);

            // Add specific error guidance based on error code
            $errorGuidance = $this->getErrorGuidance($e->getAwsErrorCode());
            if ($errorGuidance) {
                $logContext['error_guidance'] = $errorGuidance;
            }

            $this->logger->error('AWS Bedrock API error', $logContext);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getAwsErrorCode(),
                'metadata' => [
                    'model' => $model['id'],
                    'aws_error_type' => $e->getAwsErrorType(),
                    'request_id' => $e->getAwsRequestId(),
                ],
            ];
        } catch (\JsonException $e) {
            $this->logger->error('JSON encoding/decoding error in Bedrock service', [
                'error' => $e->getMessage(),
                'model' => $model['id'],
                'model_key' => $modelKey ?? $this->modelKey,
                'json_error_code' => $e->getCode(),
                'json_error_message' => json_last_error_msg(),
                'prompt_length' => \strlen($prompt),
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'request_body' => json_encode($requestBody, \JSON_PRETTY_PRINT),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Invalid JSON response from AI model',
                'metadata' => [
                    'model' => $model['id'],
                    'json_error_code' => $e->getCode(),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in Bedrock service', [
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
                'model' => $model['id'],
                'model_key' => $modelKey ?? $this->modelKey,
                'prompt_length' => \strlen($prompt),
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'request_body' => json_encode($requestBody, \JSON_PRETTY_PRINT),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'previous_exception' => $e->getPrevious() ? [
                    'class' => $e->getPrevious()::class,
                    'message' => $e->getPrevious()->getMessage(),
                    'file' => $e->getPrevious()->getFile(),
                    'line' => $e->getPrevious()->getLine(),
                ] : null,
            ]);

            return [
                'success' => false,
                'error' => 'Unexpected error occurred',
                'metadata' => [
                    'model' => $model['id'],
                    'exception_class' => $e::class,
                ],
            ];
        }
    }

    /**
     * Builds unified Converse API request for all models
     * Uses the same format regardless of the underlying model.
     *
     * @return array<string, mixed>
     */
    private function buildConverseRequest(string $prompt, int $maxTokens, float $temperature, ?string $reasoningEffort = null, bool $supportsTemperature = true): array
    {
        $inferenceConfig = [
            'maxTokens' => $maxTokens,
        ];

        if ($supportsTemperature) {
            $inferenceConfig['temperature'] = $temperature;
        }

        $request = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'inferenceConfig' => $inferenceConfig,
        ];

        if (null !== $reasoningEffort) {
            $request['additionalModelRequestFields'] = [
                'reasoning_effort' => $reasoningEffort,
            ];
        }

        return $request;
    }

    /**
     * Parses unified Converse API response from all models
     * Uses the same parsing method regardless of the underlying model.
     *
     * @param array<string, mixed> $result
     */
    private function parseConverseResponse(array $result): string
    {
        $content = $result['output']['message']['content'] ?? [];

        // Handle different response formats - some models return reasoning content first
        foreach ($content as $item) {
            if (isset($item['text'])) {
                return $item['text'];
            }
        }

        return '';
    }

    /** Provides specific guidance based on AWS error codes */
    private function getErrorGuidance(?string $errorCode): ?string
    {
        return match ($errorCode) {
            'ValidationException' => 'Check request parameters - likely invalid model ID, reasoning config, or parameter values',
            'AccessDeniedException' => 'Check IAM permissions for Bedrock service and model access',
            'ResourceNotFoundException' => 'Model not found - verify model ID is correct and available in your region',
            'ThrottlingException' => 'Rate limit exceeded - implement exponential backoff retry logic',
            'ServiceQuotaExceededException' => 'Service quota exceeded - check your Bedrock usage limits',
            'ModelTimeoutException' => 'Model processing timeout - try reducing prompt size or max tokens',
            'ModelErrorException' => 'Model processing error - check prompt content and parameters',
            'InternalServerException' => 'AWS internal error - retry with exponential backoff',
            'ModelNotReadyException' => 'Model is not ready - wait and retry',
            'ModelStreamErrorException' => 'Streaming error - check if streaming is properly configured',
            default => null,
        };
    }
}
