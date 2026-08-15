<?php

declare(strict_types=1);

namespace App\Service\CaptainScore;

/**
 * Immutable result of a Captain score computation for a coaster set.
 *
 * Quality and Strength scores are null when the set is empty, so the
 * template layer can render an "n/a" state without guessing a default.
 *
 * `legacyStrengthScore` exposes the original log-scale CSS formula
 * (`CQS + 100·log_100(n)`) for debugging or A/B comparison — UI surfaces
 * the new count-shaped `strengthScore` by default.
 */
class CaptainScores
{
    public function __construct(
        public readonly int $coasterCount,
        public readonly ?float $qualityScore,
        public readonly ?float $strengthScore,
        public readonly ?float $legacyStrengthScore = null,
    ) {
    }
}
