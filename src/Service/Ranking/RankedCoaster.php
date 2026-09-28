<?php

declare(strict_types=1);

namespace App\Service\Ranking;

/**
 * A ranked coaster, as RankingCalculator computes it.
 */
final readonly class RankedCoaster
{
    public function __construct(
        public int $coaster,
        public float $score,
        // Valid duels: won + lost + tied
        public int $duels,
        public int $won,
        public int $lost,
        public int $tied,
        // Riders who compared it with another coaster
        public int $riders,
    ) {
    }
}
