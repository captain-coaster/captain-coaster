<?php

declare(strict_types=1);

namespace App\DTO\Profile;

use App\Entity\Tag;

/**
 * How a member rates: the distribution of their ratings and the tags they use most.
 */
final readonly class ProfileRatings
{
    /**
     * @param list<int>                         $counts ten entries, ratings 0.5 to 5 in steps of 0.5
     * @param list<array{tag: Tag, count: int}> $pros   most used first
     * @param list<array{tag: Tag, count: int}> $cons
     */
    public function __construct(
        public array $counts,
        public float $average,
        public array $pros,
        public array $cons,
    ) {
    }

    public function max(): int
    {
        return max([0, ...$this->counts]);
    }
}
