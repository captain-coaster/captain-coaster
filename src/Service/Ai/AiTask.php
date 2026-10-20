<?php

declare(strict_types=1);

namespace App\Service\Ai;

/**
 * What each AI feature runs on. Moving a feature to another model or effort is a change here.
 * The value is stored with each call in ai_call.
 */
enum AiTask: string
{
    case CoasterSummary = 'coaster_summary';
    case ReviewModeration = 'review_moderation';
    case ImageModeration = 'image_moderation';

    public function model(): AiModel
    {
        return match ($this) {
            self::CoasterSummary, self::ImageModeration => AiModel::Gpt56Luna,
            self::ReviewModeration => AiModel::Gpt6Luna,
        };
    }

    /**
     * none, low, medium, high, xhigh or max. Medium is what GPT-5.6 Luna runs on when none is
     * sent, so every feature keeps the behavior it had before the effort was set.
     */
    public function reasoningEffort(): string
    {
        return match ($this) {
            self::CoasterSummary, self::ReviewModeration, self::ImageModeration => 'medium',
        };
    }

    /** Reasoning tokens count against it: too low truncates the JSON answer. */
    public function maxTokens(): int
    {
        return match ($this) {
            self::CoasterSummary => 2000,
            // GPT-6 Luna reasons longer on borderline reviews: 500 cut the answer on ~1% of them.
            self::ReviewModeration => 1500,
            self::ImageModeration => 800,
        };
    }
}
