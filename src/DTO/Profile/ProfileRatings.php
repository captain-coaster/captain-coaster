<?php

declare(strict_types=1);

namespace App\DTO\Profile;

/**
 * How a member rates: the distribution of their ratings and their average.
 */
final readonly class ProfileRatings
{
    /** @param list<int> $counts ten entries, ratings 0.5 to 5 in steps of 0.5 */
    public function __construct(
        public array $counts,
        public float $average,
    ) {
    }

    public function max(): int
    {
        return max([0, ...$this->counts]);
    }
}
