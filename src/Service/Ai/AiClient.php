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
 * cost; a failure is logged here and thrown as AiCallFailed.
 */
class AiClient
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
     * @throws AiCallFailed
     */
    public function run(
        AiTask $task,
        string $instructions,
        string $input,
        array $outputSchema,
        ?int $subjectId = null,
        ?string $language = null,
        ?string $jpegBytes = null,
        ?AiModel $model = null,
    ): AiResult {
        $model ??= $task->model();
        $context = ['task' => $task->value, 'model' => $model->value, 'subject_id' => $subjectId];

        $request = $this->buildRequest($task, $model, $instructions, $input, $outputSchema, $jpegBytes);
        try {
            $response = $this->bedrockClient->converse($request)->toArray();
        } catch (AwsException $e) {
            $errorCode = $e->getAwsErrorCode() ?? 'AwsException';
            $this->logger->error(\sprintf('Bedrock error: %s', $errorCode), $context + ['error' => $e->getAwsErrorMessage() ?? $e->getMessage(), 'request_id' => $e->getAwsRequestId()]);
            $this->record($task, $model, $subjectId, $language, null, $errorCode);

            throw new AiCallFailed($task, $errorCode, $e->getAwsErrorMessage() ?? $e->getMessage(), $e);
        } catch (\Throwable $e) {
            // Not an AwsException: expired credentials, a network or SDK error.
            $errorCode = new \ReflectionClass($e)->getShortName();
            $this->logger->error(\sprintf('Bedrock call failed: %s', $errorCode), $context + ['error' => $e->getMessage()]);
            $this->record($task, $model, $subjectId, $language, null, $errorCode);

            throw new AiCallFailed($task, $errorCode, $e->getMessage(), $e);
        }

        $usage = $this->usage($model, $response);

        // Truncated (max_tokens) or filtered: the answer is incomplete.
        $stopReason = $response['stopReason'] ?? null;
        if (null !== $stopReason && 'end_turn' !== $stopReason) {
            $this->logger->error(\sprintf('Bedrock answer stopped early: %s', $stopReason), $context + ['output_tokens' => $usage['outputTokens']]);
            $this->record($task, $model, $subjectId, $language, $usage, $stopReason);

            throw new AiCallFailed($task, $stopReason, 'the answer stopped early');
        }

        $text = $this->extractText($response);
        try {
            $data = json_decode($text, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        if (!\is_array($data)) {
            $this->logger->error('Bedrock answer is not a JSON object', $context + ['content' => mb_substr($text, 0, 500)]);
            $this->record($task, $model, $subjectId, $language, $usage, 'InvalidJson');

            throw new AiCallFailed($task, 'InvalidJson', 'the answer is not a JSON object');
        }

        $this->record($task, $model, $subjectId, $language, $usage, null);

        return new AiResult($data, $model, ...$usage);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array{inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, costUsd: float, latencyMs: ?int}
     */
    private function usage(AiModel $model, array $response): array
    {
        $usage = $response['usage'] ?? [];
        $inputTokens = (int) ($usage['inputTokens'] ?? 0);
        $outputTokens = (int) ($usage['outputTokens'] ?? 0);
        $cacheReadTokens = (int) ($usage['cacheReadInputTokens'] ?? 0);
        $cacheWriteTokens = (int) ($usage['cacheWriteInputTokens'] ?? 0);

        return [
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
            'cacheReadTokens' => $cacheReadTokens,
            'cacheWriteTokens' => $cacheWriteTokens,
            'costUsd' => round($model->costUsd($inputTokens, $outputTokens, $cacheReadTokens, $cacheWriteTokens), 6),
            'latencyMs' => isset($response['metrics']['latencyMs']) ? (int) $response['metrics']['latencyMs'] : null,
        ];
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
                // Explicit mode without breakpoints: no cache-write surcharge (1.25x input), which implicit
                // mode bills on every call while our prompts are almost never re-sent. Reads still happen.
                'prompt_cache_options' => ['mode' => 'explicit'],
            ],
        ];
    }

    /**
     * The answer is the first text block; reasoning models may put a reasoningContent block first.
     *
     * @param array<string, mixed> $response
     */
    private function extractText(array $response): string
    {
        foreach ($response['output']['message']['content'] ?? [] as $block) {
            if (isset($block['text'])) {
                return trim($block['text']);
            }
        }

        return '';
    }

    /** @param array{inputTokens: int, outputTokens: int, cacheReadTokens: int, cacheWriteTokens: int, costUsd: float, latencyMs: ?int}|null $usage */
    private function record(AiTask $task, AiModel $model, ?int $subjectId, ?string $language, ?array $usage, ?string $errorCode): void
    {
        try {
            $this->aiCallRepository->record([
                'task' => $task->value,
                'model' => $model->value,
                'subject_id' => $subjectId,
                'language' => $language,
                'success' => null === $errorCode,
                'error_code' => null !== $errorCode ? mb_substr($errorCode, 0, 64) : null,
                'input_tokens' => $usage['inputTokens'] ?? 0,
                'output_tokens' => $usage['outputTokens'] ?? 0,
                'cache_read_tokens' => $usage['cacheReadTokens'] ?? 0,
                'cache_write_tokens' => $usage['cacheWriteTokens'] ?? 0,
                'cost_usd' => $usage['costUsd'] ?? 0.0,
                'latency_ms' => $usage['latencyMs'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Losing a cost line must not lose the answer that was paid for.
            $this->logger->error('Could not record the AI call', ['task' => $task->value, 'error' => $e->getMessage()]);
        }
    }
}
