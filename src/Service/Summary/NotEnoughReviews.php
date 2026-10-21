<?php

declare(strict_types=1);

namespace App\Service\Summary;

/**
 * Too few reviews to summarize a coaster in a language: an expected outcome, not an error.
 */
final class NotEnoughReviews extends \RuntimeException
{
    public function __construct(public readonly int $reviewCount)
    {
        parent::__construct(\sprintf(
            'only %d reviews (need at least %d in the language and %d in all)',
            $reviewCount,
            CoasterSummaryGenerator::MIN_NATIVE_REVIEWS_REQUIRED,
            CoasterSummaryGenerator::MIN_REVIEWS_REQUIRED,
        ));
    }
}
