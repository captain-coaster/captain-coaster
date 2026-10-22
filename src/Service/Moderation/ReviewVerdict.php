<?php

declare(strict_types=1);

namespace App\Service\Moderation;

/**
 * What the AI made of a review text.
 */
final readonly class ReviewVerdict
{
    /** @param ?string $explanation one sentence for the moderator, null when the rule is none */
    public function __construct(
        public string $language,
        public ReviewRule $rule,
        public ?string $explanation,
    ) {
    }
}
