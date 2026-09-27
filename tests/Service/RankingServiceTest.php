<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RankingService;
use PHPUnit\Framework\TestCase;

class RankingServiceTest extends TestCase
{
    /**
     * Coaster ids 1..60, ranked in that order.
     *
     * @return array<int, float>
     */
    private function ranking(): array
    {
        return array_fill_keys(range(1, 60), 50.0);
    }

    /**
     * Duels between $a and $b: $aWins comparisons won by $a, $bWins by $b (ties already split in half).
     *
     * @return array<int, array<int, float>>
     */
    private function duel(int $a, int $b, float $aWins, float $bWins): array
    {
        return [$a => [$b => $aWins], $b => [$a => $bWins]];
    }

    public function testFeaturedDuelCountsRidersAndBetterRankedWinsWithTiesAsHalf(): void
    {
        // 40 riders compared #4 and #25: 25 preferred #4, 14 preferred #25, 1 tie
        $this->assertSame(
            ['first' => 4, 'second' => 25, 'comparisons' => 40, 'firstWins' => 25.5],
            RankingService::featuredDuel($this->ranking(), $this->duel(4, 25, 25.5, 14.5)),
        );
    }

    public function testFeaturedDuelSkipsTopTwoCloseRanksFewRidersAndUpsets(): void
    {
        $duels = array_replace(
            $this->duel(1, 20, 30.0, 10.0),  // #1 is never featured
            $this->duel(5, 10, 30.0, 10.0),  // only 5 places apart
            $this->duel(6, 30, 20.0, 5.0),   // 25 riders
            $this->duel(7, 30, 15.0, 25.0),  // #30 wins the duel
            $this->duel(21, 40, 30.0, 10.0), // #21 is past the featured range
        );

        $this->assertNull(RankingService::featuredDuel($this->ranking(), $duels));
    }

    public function testRiderComparisonsCountTopPairsOnceWhenAlsoRated(): void
    {
        $this->assertSame(45, RankingService::riderComparisons(10, 0, 0));
        // 10 ratings, a Top of 5 of them: the Top's 10 pairs replace 10 rating pairs
        $this->assertSame(45, RankingService::riderComparisons(10, 5, 5));
        // 10 ratings, a Top of 5 unrated coasters: 45 + 10
        $this->assertSame(55, RankingService::riderComparisons(10, 5, 0));
    }

    public function testFeaturedDuelPicksAmongTheCandidates(): void
    {
        $duels = $this->duel(3, 13, 30.0, 10.0) + $this->duel(20, 60, 30.0, 10.0);

        $pair = RankingService::featuredDuel($this->ranking(), $duels);

        $this->assertContains([$pair['first'], $pair['second']], [[3, 13], [20, 60]]);
    }
}
