<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Repository\AiCallRepository;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;

/**
 * Calls a Bedrock model through the Converse API and returns its answer as decoded JSON.
 *
 * The instructions go in the system prompt, ahead of the per-call input, so the shared prefix is
 * eligible for Bedrock's implicit prompt caching. The answer is constrained to the caller's JSON
 * schema (strict structured output). Every call, failed or not, is recorded in ai_call with its
 * cost.
 */
class BedrockService
{
    public function __construct(
        private readonly BedrockRuntimeClient $bedrockClient,
        private readonly AiCallRepository $aiCallRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $outputSchema JSON schema of the answer, strict mode: every property
     *                                           required, additionalProperties false
     * @param int|null             $subjectId    what the call is about, recorded with it (see AiCall)
     * @param string|null          $jpegBytes    an image placed ahead of $input, for vision calls
     * @param AiModel|null         $model        overrides the task's model, for comparisons
     *
     * @return array{success: true, data: array<string, mixed>, metadata: array<string, mixed>}|array{success: false, error: string, error_code: string|null, metadata: array<string, mixed>}
     */
    public function invoke(
        AiTask $task,
        string $instructions,
        string $input,
        array $outputSchema,
        ?int $subjectId = null,
        ?string $language = null,
        ?string $jpegBytes = null,
        ?AiModel $model = null,
    ): array {
        $model ??= $task->model();
        $request = $this->buildRequest($task, $model, $instructions, $input, $outputSchema, $jpegBytes);

        try {
            $result = $this->bedrockClient->converse($request)->toArray();
        } catch (AwsException $e) {
            $this->logger->error(\sprintf('AWS Bedrock API error: %s (%s)', $e->getAwsErrorCode() ?? 'unknown', $e->getStatusCode() ?? 'no status'), [
                'task' => $task->value,
                'model' => $model->value,
                'subject_id' => $subjectId,
                'error' => $e->getAwsErrorMessage() ?? $e->getMessage(),
                'request_id' => $e->getAwsRequestId(),
                'error_guidance' => $this->getErrorGuidance($e->getAwsErrorCode()),
            ]);
            $this->record($task, $model, $subjectId, $language, [], $e->getAwsErrorCode() ?? 'AwsException');

            return $this->failure($e->getAwsErrorMessage() ?? $e->getMessage(), $e->getAwsErrorCode(), $task, $model);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in Bedrock service', [
                'task' => $task->value,
                'model' => $model->value,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
            $this->record($task, $model, $subjectId, $language, [], 'Exception');

            return $this->failure('Unexpected error occurred', null, $task, $model);
        }

        $metadata = $this->metadata($task, $model, $result);
        $text = $this->extractText($result);

        // Truncated (max_tokens) or filtered: the answer is incomplete, report why it stopped.
        if (null !== $metadata['stop_reason'] && 'end_turn' !== $metadata['stop_reason']) {
            $this->logger->warning(\sprintf('Bedrock answer stopped early: %s', $metadata['stop_reason']), ['task' => $task->value, 'subject_id' => $subjectId] + $metadata);
            $this->record($task, $model, $subjectId, $language, $metadata, $metadata['stop_reason']);

            return $this->failure('Answer stopped early: '.$metadata['stop_reason'], $metadata['stop_reason'], $task, $model, $metadata);
        }

        try {
            $data = json_decode($text, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        if (!\is_array($data)) {
            $this->logger->warning('Bedrock answer is not a JSON object', ['task' => $task->value, 'subject_id' => $subjectId, 'content' => mb_substr($text, 0, 500)] + $metadata);
            $this->record($task, $model, $subjectId, $language, $metadata, 'InvalidJson');

            return $this->failure('Answer is not a JSON object', 'InvalidJson', $task, $model, $metadata);
        }

        $this->record($task, $model, $subjectId, $language, $metadata, null);

        return ['success' => true, 'data' => $data, 'metadata' => $metadata];
    }

    /**
     * @param array<string, mixed> $outputSchema
     *
     * @return array<string, mixed>
     */
    private function buildRequest(AiTask $task, AiModel $model, string $instructions, string $input, array $outputSchema, ?string $jpegBytes): array
    {
        // Image ahead of the text, per the Converse API convention.
        $content = [];
        if (null !== $jpegBytes) {
            $content[] = ['image' => ['format' => 'jpeg', 'source' => ['bytes' => $jpegBytes]]];
        }
        $content[] = ['text' => $input];

        return [
            'modelId' => $model->bedrockId(),
            'system' => [['text' => $instructions]],
            'messages' => [['role' => 'user', 'content' => $content]],
            'inferenceConfig' => ['maxTokens' => $task->maxTokens()],
            'outputConfig' => [
                'textFormat' => [
                    'type' => 'json_schema',
                    'structure' => ['jsonSchema' => ['name' => $task->value, 'schema' => json_encode($outputSchema, \JSON_THROW_ON_ERROR)]],
                ],
            ],
            'additionalModelRequestFields' => [
                'reasoning' => ['effort' => $task->reasoningEffort()],
                'text' => ['format' => ['strict' => true]],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array{task: string, model_key: string, model: string, latency_ms: int|null, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, cost_usd: float, stop_reason: string|null}
     */
    private function metadata(AiTask $task, AiModel $model, array $result): array
    {
        $usage = $result['usage'] ?? [];
        $inputTokens = (int) ($usage['inputTokens'] ?? 0);
        $outputTokens = (int) ($usage['outputTokens'] ?? 0);
        $cacheReadTokens = (int) ($usage['cacheReadInputTokens'] ?? 0);
        $cacheWriteTokens = (int) ($usage['cacheWriteInputTokens'] ?? 0);

        return [
            'task' => $task->value,
            'model_key' => $model->value,
            'model' => $model->bedrockId(),
            'latency_ms' => isset($result['metrics']['latencyMs']) ? (int) $result['metrics']['latencyMs'] : null,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'cache_write_tokens' => $cacheWriteTokens,
            'cost_usd' => round($model->costUsd($inputTokens, $outputTokens, $cacheReadTokens, $cacheWriteTokens), 6),
            'stop_reason' => $result['stopReason'] ?? null,
        ];
    }

    /**
     * The answer is the first text block; reasoning models may put a reasoningContent block first.
     *
     * @param array<string, mixed> $result
     */
    private function extractText(array $result): string
    {
        foreach ($result['output']['message']['content'] ?? [] as $block) {
            if (isset($block['text'])) {
                return trim($block['text']);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $metadata */
    private function record(AiTask $task, AiModel $model, ?int $subjectId, ?string $language, array $metadata, ?string $errorCode): void
    {
        try {
            $this->aiCallRepository->record([
                'task' => $task->value,
                'model' => $model->value,
                'subject_id' => $subjectId,
                'language' => $language,
                'success' => null === $errorCode,
                'error_code' => null !== $errorCode ? mb_substr($errorCode, 0, 64) : null,
                'input_tokens' => $metadata['input_tokens'] ?? 0,
                'output_tokens' => $metadata['output_tokens'] ?? 0,
                'cache_read_tokens' => $metadata['cache_read_tokens'] ?? 0,
                'cache_write_tokens' => $metadata['cache_write_tokens'] ?? 0,
                'cost_usd' => $metadata['cost_usd'] ?? 0.0,
                'latency_ms' => $metadata['latency_ms'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Losing a cost line must not lose the answer that was paid for.
            $this->logger->error('Could not record the AI call', ['task' => $task->value, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{success: false, error: string, error_code: string|null, metadata: array<string, mixed>}
     */
    private function failure(string $error, ?string $errorCode, AiTask $task, AiModel $model, array $metadata = []): array
    {
        return [
            'success' => false,
            'error' => $error,
            'error_code' => $errorCode,
            'metadata' => $metadata + ['task' => $task->value, 'model_key' => $model->value, 'model' => $model->bedrockId()],
        ];
    }

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
            default => null,
        };
    }
}
