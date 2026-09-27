<?php

declare(strict_types=1);

namespace App\Tests\Service\Ranking;

use App\Service\Ranking\RankedCoaster;
use App\Service\Ranking\RankingCalculator;
use PHPUnit\Framework\TestCase;

class RankingCalculatorTest extends TestCase
{
    // Big enough for every coaster of the field to be ranked, elite ones included
    private const int FIELD = RankingCalculator::MIN_DUELS_ELITE + 1;

    /**
     * Riders who all rate coasters $from.. in that order, best first.
     *
     * @return list<int> the coasters
     */
    private function field(RankingCalculator $calculator, int $count = self::FIELD, int $riders = RankingCalculator::MIN_COMPARISONS, int $from = 1): array
    {
        $coasters = range($from, $from + $count - 1);
        $ratings = [];
        foreach ($coasters as $index => $coaster) {
            $ratings[$coaster] = (float) ($count - $index);
        }

        $this->ride($calculator, $riders, $ratings);

        return $coasters;
    }

    /**
     * @param array<int, float> $ratings
     * @param array<int, int>   $top
     */
    private function ride(RankingCalculator $calculator, int $riders, array $ratings, array $top = []): void
    {
        for ($i = 0; $i < $riders; ++$i) {
            $calculator->addRider($ratings, $top);
        }
    }

    /**
     * @param list<RankedCoaster> $coasters
     *
     * @return list<int>
     */
    private function ids(array $coasters): array
    {
        return array_map(static fn (RankedCoaster $coaster): int => $coaster->coaster, $coasters);
    }

    public function testRanksByShareOfDuelsWon(): void
    {
        $calculator = new RankingCalculator();
        $field = $this->field($calculator);

        $coasters = $calculator->compute()->coasters;

        $this->assertSame($field, $this->ids($coasters));
        $this->assertEquals([100.0, self::FIELD - 1, self::FIELD - 1, 0], [$coasters[0]->score, $coasters[0]->duels, $coasters[0]->won, $coasters[0]->lost]);
        $last = $coasters[\count($coasters) - 1];
        $this->assertEquals([0.0, 0], [$last->score, $last->won]);
        $this->assertSame(RankingCalculator::MIN_COMPARISONS, $coasters[0]->riders);
    }

    public function testEliteScoreNeedsMoreDuels(): void
    {
        $calculator = new RankingCalculator();
        // MIN_DUELS duels each: coaster k wins 401 - k of them, a score of 95 or more up to #21
        $this->field($calculator, RankingCalculator::MIN_DUELS + 1);

        $ranked = $this->ids($calculator->compute()->coasters);

        $this->assertSame(22, $ranked[0]);
        $this->assertCount(RankingCalculator::MIN_DUELS + 1 - 21, $ranked);
    }

    public function testDuelNeedsMinComparisons(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator, riders: RankingCalculator::MIN_COMPARISONS - 1);

        $result = $calculator->compute();

        $this->assertSame([], $result->coasters);
        $this->assertSame(0, $result->comparisons);
    }

    public function testSameRatingIsATieCountingHalf(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator);
        // 2 led 3 by 4 to 0: 4 ties (2 each) and 4 riders preferring 3 make it 6 to 6
        $this->ride($calculator, 4, [2 => 3.0, 3 => 3.0]);
        $this->ride($calculator, 4, [2 => 1.0, 3 => 2.0]);

        $coasters = $calculator->compute()->coasters;

        $this->assertSame([1, 1], [$coasters[1]->tied, $coasters[2]->tied]);
        $this->assertSame([2, 3], [$coasters[1]->coaster, $coasters[2]->coaster]);
    }

    public function testTopSettlesThePairsItHolds(): void
    {
        $settled = new RankingCalculator();
        $this->field($settled);
        // Ten riders rate 3 above 2, but their Top puts 2 first: the Top wins
        $this->ride($settled, 10, [2 => 1.0, 3 => 5.0], [2 => 1, 3 => 2]);

        $turned = new RankingCalculator();
        $this->field($turned);
        $this->ride($turned, 10, [2 => 1.0, 3 => 5.0]);

        $this->assertSame([1, 2, 3], \array_slice($this->ids($settled->compute()->coasters), 0, 3));
        $this->assertSame([1, 3, 2], \array_slice($this->ids($turned->compute()->coasters), 0, 3));
    }

    public function testTopComparesCoastersNotRated(): void
    {
        $calculator = new RankingCalculator();
        $coasters = range(1, self::FIELD);
        $this->ride($calculator, RankingCalculator::MIN_COMPARISONS, [], array_flip($coasters));

        $result = $calculator->compute();

        $this->assertSame($coasters, $this->ids($result->coasters));
        $this->assertSame(RankingCalculator::MIN_COMPARISONS, $result->contributors);
    }

    public function testDroppedCoasterNoLongerCountsForOthers(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator);
        // 9001 beats coasters 1-10 but has only 10 duels
        $this->ride($calculator, RankingCalculator::MIN_COMPARISONS, array_fill_keys(range(1, 10), 1.0) + [9001 => 5.0]);

        $coasters = $calculator->compute()->coasters;

        $this->assertNotContains(9001, $this->ids($coasters));
        $this->assertSame([1, 0], [$coasters[0]->coaster, $coasters[0]->lost]);
    }

    public function testDroppingACoasterCanDropAnother(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator);
        // 9002 faces 10 coasters and 9001; 9001 faces MIN_DUELS - 1 coasters and 9002: MIN_DUELS until 9002 drops
        $this->ride($calculator, RankingCalculator::MIN_COMPARISONS, array_fill_keys(range(1, 10), 3.0) + [9002 => 1.0]);
        $this->ride($calculator, RankingCalculator::MIN_COMPARISONS, array_fill_keys(range(1, RankingCalculator::MIN_DUELS - 1), 3.0) + [9001 => 1.0]);
        $this->ride($calculator, RankingCalculator::MIN_COMPARISONS, [9001 => 1.0, 9002 => 2.0]);

        $ranked = $this->ids($calculator->compute()->coasters);

        $this->assertNotContains(9002, $ranked);
        $this->assertNotContains(9001, $ranked);
    }

    public function testExactTiesAreBrokenByDuelsThenId(): void
    {
        $calculator = new RankingCalculator();
        // Everyone ties: every score is 50, with the same duels
        $coasters = range(10, 10 + RankingCalculator::MIN_DUELS + 2);
        $this->ride($calculator, RankingCalculator::MIN_COMPARISONS, array_fill_keys(array_reverse($coasters), 3.0));

        $this->assertSame($coasters, $this->ids($calculator->compute()->coasters));
    }

    public function testRiderNeedsTwoCoastersToContribute(): void
    {
        $calculator = new RankingCalculator();
        $calculator->addRider([1 => 4.0], []);
        $calculator->addRider([], [1 => 1]);
        $calculator->addRider([1 => 4.0], [2 => 1]);

        $this->assertSame(1, $calculator->compute()->contributors);
    }

    public function testComparisonsCountEachValidDuelsRidersOncePerSide(): void
    {
        $calculator = new RankingCalculator();
        $count = RankingCalculator::MIN_DUELS + 1;
        $this->field($calculator, $count, 5);

        $this->assertSame($count * ($count - 1) / 2 * 5 * 2, $calculator->compute()->comparisons);
    }

    public function testFeaturedDuelCountsRidersAndBetterRankedWinsWithTiesAsHalf(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator);
        // #4 against #25: 4 riders from the field, 25 more prefer #4, 10 prefer #25, 1 tie
        $this->ride($calculator, 25, [4 => 5.0, 25 => 1.0]);
        $this->ride($calculator, 10, [4 => 1.0, 25 => 5.0]);
        $this->ride($calculator, 1, [4 => 3.0, 25 => 3.0]);

        $this->assertSame(
            ['first' => 4, 'second' => 25, 'comparisons' => 40, 'firstWins' => 29.5],
            $calculator->compute()->featuredDuel,
        );
    }

    public function testFeaturedDuelSkipsTopTwoCloseRanksAndUpsets(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator);
        $this->ride($calculator, 30, [1 => 5.0, 20 => 1.0]);  // #1 is never featured
        $this->ride($calculator, 30, [5 => 5.0, 10 => 1.0]);  // only 5 places apart
        $this->ride($calculator, 30, [7 => 1.0, 30 => 5.0]);  // #30 wins the duel
        $this->ride($calculator, 30, [21 => 5.0, 40 => 1.0]); // #21 is past the featured range

        $this->assertNull($calculator->compute()->featuredDuel);
    }

    public function testNoFeaturedDuelWithoutEnoughRiders(): void
    {
        $calculator = new RankingCalculator();
        $this->field($calculator, riders: RankingCalculator::FEATURED_DUEL_MIN_RIDERS - 1);

        $this->assertNull($calculator->compute()->featuredDuel);
    }
}
