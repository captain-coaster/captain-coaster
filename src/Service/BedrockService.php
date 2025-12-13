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
        'claude-haiku-4.5' => [
            'id' => 'global.anthropic.claude-haiku-4-5-20251001-v1:0',
            'input_cost_per_1k' => 0.001,
            'output_cost_per_1k' => 0.005,
        ],
        'gpt-oss-120b' => [
            'id' => 'openai.gpt-oss-120b-1:0',
            'input_cost_per_1k' => 0.00015,
            'output_cost_per_1k' => 0.0006,
        ],
        'nova2-lite' => [
            'id' => 'global.amazon.nova-2-lite-v1:0',
            'input_cost_per_1k' => 0.0003,
            'output_cost_per_1k' => 0.0025,
        ],
        'mistral' => [
            'id' => 'mistral.mistral-large-3-675b-instruct',
            'input_cost_per_1k' => 0.0005,
            'output_cost_per_1k' => 0.0015,
        ],
    ];

    private const DEFAULT_MODEL = 'mistral';

    public function __construct(
        private BedrockRuntimeClient $bedrockClient,
        private LoggerInterface $logger,
        private string $modelKey = self::DEFAULT_MODEL
    ) {
    }

    public function invokeModel(string $prompt, ?string $modelKey = null, int $maxTokens = 1000, float $temperature = 0.6, bool $enableReasoning = false): array
    {
        $model = self::MODELS[$modelKey ?? $this->modelKey];

        try {
            $requestBody = $this->buildConverseRequest($prompt, $maxTokens, $temperature, $enableReasoning);

            $response = $this->bedrockClient->converse($requestBody + ['modelId' => $model['id']]);

            $result = $response->toArray();
            $metadata = $result['@metadata'];

            // Debug: Log the response structure to understand token location
            $this->logger->debug('Converse API response structure', [
                'response_keys' => array_keys($result),
                'usage_data' => $result['usage'] ?? 'not found',
                'metadata_headers' => array_keys($metadata['headers'] ?? []),
            ]);

            // For Converse API, token usage is in the 'usage' field, not headers
            $usage = $result['usage'] ?? [];
            $inputTokens = $usage['inputTokens'] ?? 0;
            $outputTokens = $usage['outputTokens'] ?? 0;

            // Latency might still be in headers
            $latencyMs = $metadata['headers']['x-amzn-bedrock-invocation-latency'] ?? null;

            $inputCost = ($inputTokens / 1000) * $model['input_cost_per_1k'];
            $outputCost = ($outputTokens / 1000) * $model['output_cost_per_1k'];
            $totalCost = $inputCost + $outputCost;

            $metadata = [
                'model' => $model['id'],
                'latency_ms' => $latencyMs,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round($totalCost, 6),
            ];

            // Log successful requests for monitoring
            $this->logger->info('Bedrock API request successful', [
                'model' => $model['id'],
                'model_key' => $modelKey ?? $this->modelKey,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => round($totalCost, 6),
                'latency_ms' => $latencyMs,
                'enable_reasoning' => $enableReasoning,
                'prompt_length' => \strlen($prompt),
                'response_length' => \strlen($this->parseConverseResponse($result)),
            ]);

            $content = $this->parseConverseResponse($result);

            // Check if content is empty and log warning
            if (empty($content)) {
                $this->logger->warning('Bedrock returned empty content', [
                    'model' => $model['id'],
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
                'enable_reasoning' => $enableReasoning,
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
                'enable_reasoning' => $enableReasoning,
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
                'enable_reasoning' => $enableReasoning,
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
     */
    private function buildConverseRequest(string $prompt, int $maxTokens, float $temperature, bool $enableReasoning = false): array
    {
        $request = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'inferenceConfig' => [
                'maxTokens' => $maxTokens,
                'temperature' => $temperature,
            ],
        ];

        // Note: Reasoning configuration may not be supported via Converse API for Nova 2 Lite
        // Keeping this disabled until AWS documentation confirms the correct format
        // if ($enableReasoning) {
        //     $request['additionalModelRequestFields'] = [
        //         'reasoningConfig' => [
        //             'type' => 'enabled',
        //             'maxReasoningEffort' => 'medium',
        //         ],
        //     ];
        // }

        return $request;
    }

    /**
     * Parses unified Converse API response from all models
     * Uses the same parsing method regardless of the underlying model.
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

        // Fallback to original logic
        return $result['output']['message']['content'][0]['text'] ?? '';
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
