<?php

declare(strict_types=1);

namespace App\Service\Ai;

/**
 * A model's answer, decoded from its JSON, and what the call cost.
 */
final readonly class AiResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data,
        public AiModel $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $cacheReadTokens,
        public int $cacheWriteTokens,
        public float $costUsd,
        public ?int $latencyMs,
    ) {
    }

    /** One line for a console: latency, tokens and cost. */
    public function usage(): string
    {
        $cache = $this->cacheReadTokens > 0 || $this->cacheWriteTokens > 0
            ? \sprintf(' (%d cached, %d cache-write)', $this->cacheReadTokens, $this->cacheWriteTokens)
            : '';

        return \sprintf('%s, %sms, %d+%d tokens%s, $%s', $this->model->value, $this->latencyMs ?? '?', $this->inputTokens, $this->outputTokens, $cache, number_format($this->costUsd, 4));
    }
}
