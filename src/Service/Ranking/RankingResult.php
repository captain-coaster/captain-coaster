<?php

declare(strict_types=1);

namespace App\Service\Ranking;

final readonly class RankingResult
{
    /**
     * @param list<RankedCoaster>                                                     $coasters     best first
     * @param int                                                                     $comparisons  within the valid duels, counted once per side
     * @param int                                                                     $contributors riders with at least two coasters to compare
     * @param array{first: int, second: int, comparisons: int, firstWins: float}|null $featuredDuel
     */
    public function __construct(
        public array $coasters,
        public int $comparisons,
        public int $contributors,
        public ?array $featuredDuel,
    ) {
    }

    /** @return array<int, int> coaster id => rank */
    public function ranks(): array
    {
        $ranks = [];
        foreach ($this->coasters as $index => $coaster) {
            $ranks[$coaster->coaster] = $index + 1;
        }

        return $ranks;
    }
}
