<?php

declare(strict_types=1);

namespace App\Service\Ai;

/**
 * Bedrock models the site can call. Prices are Global cross-Region, short context, in USD per
 * million tokens, from each model card:
 * https://docs.aws.amazon.com/bedrock/latest/userguide/model-card-openai-gpt-56-luna.html
 * https://docs.aws.amazon.com/bedrock/latest/userguide/model-card-openai-gpt-6-luna.html.
 *
 * Neither model accepts a temperature. Reasoning tokens are billed as output.
 */
enum AiModel: string
{
    case Gpt56Luna = 'gpt-5.6-luna';
    case Gpt6Luna = 'gpt-6-luna';

    public function bedrockId(): string
    {
        return match ($this) {
            self::Gpt56Luna => 'global.openai.gpt-5.6-luna',
            self::Gpt6Luna => 'global.openai.gpt-6-luna',
        };
    }

    /** @return array{input: float, output: float, cache_read: float, cache_write: float} */
    public function pricePerMillionTokens(): array
    {
        return match ($this) {
            self::Gpt56Luna => ['input' => 0.20, 'output' => 1.20, 'cache_read' => 0.02, 'cache_write' => 0.25],
            self::Gpt6Luna => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125],
        };
    }

    public function costUsd(int $inputTokens, int $outputTokens, int $cacheReadTokens = 0, int $cacheWriteTokens = 0): float
    {
        $price = $this->pricePerMillionTokens();

        return ($inputTokens * $price['input']
            + $outputTokens * $price['output']
            + $cacheReadTokens * $price['cache_read']
            + $cacheWriteTokens * $price['cache_write']) / 1_000_000;
    }
}
