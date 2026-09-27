<?php

declare(strict_types=1);

namespace App\Tests\Service\Ranking;

use App\Service\Ranking\RankingReport;
use PHPUnit\Framework\TestCase;

class RankingReportTest extends TestCase
{
    /**
     * Coasters $from.. ranked in that order.
     *
     * @return array<int, int>
     */
    private function ranks(int $count, int $from = 1): array
    {
        return array_combine(range($from, $from + $count - 1), range(1, $count));
    }

    public function testAQuietMonthHasNoAnomaly(): void
    {
        $previous = $this->ranks(1000);
        // 2 leave, 10 newcomers at the bottom, #500 and #501 swap
        $ranks = $this->ranks(998);
        foreach (range(2001, 2010) as $index => $coaster) {
            $ranks[$coaster] = 999 + $index;
        }
        [$ranks[500], $ranks[501]] = [501, 500];

        $report = RankingReport::compare($ranks, $previous, 1_010_000, 1_000_000);

        $this->assertSame([], $report->anomalies);
        $this->assertSame([1000, 1008], [$report->rankedBefore, $report->rankedNow]);
        $this->assertSame(array_combine(range(2001, 2010), range(999, 1008)), $report->entered);
        $this->assertSame([999 => 999, 1000 => 1000], $report->left);
        $this->assertSame([], $report->bigMoves);
    }

    public function testBigMovesWeighTheTopMore(): void
    {
        $previous = $this->ranks(1000);
        $ranks = $previous;
        // Swapped pairs: #30 and #55 (×1.83), #100 and #130 (×1.3), #2 and #6 (×3 but 4 places), #800 and #850 (×1.06)
        foreach ([[30, 55], [100, 130], [2, 6], [800, 850]] as [$a, $b]) {
            [$ranks[$a], $ranks[$b]] = [$b, $a];
        }

        $report = RankingReport::compare($ranks, $previous, 0, 0);

        $this->assertSame([[30, 30, 55], [55, 55, 30], [100, 100, 130], [130, 130, 100]], $report->bigMoves);
    }

    public function testTop10ComesWithItsPreviousRanks(): void
    {
        $previous = $this->ranks(20);
        $ranks = $previous;
        [$ranks[1], $ranks[2]] = [2, 1];
        unset($ranks[10]);
        $ranks[5000] = 10;

        $report = RankingReport::compare($ranks, $previous, 0, 0);

        $this->assertSame([[2, 1, 2], [1, 2, 1]], \array_slice($report->top10, 0, 2));
        $this->assertSame([5000, 10, null], $report->top10[9]);
        $this->assertTrue($report->top10Changed());
        $this->assertFalse(RankingReport::compare($previous, $previous, 0, 0)->top10Changed());
    }

    public function testFlagsEachAnomaly(): void
    {
        $previous = $this->ranks(1000);
        // 3 newcomers take the podium, 103 leave, the others move down 3 places
        $ranks = [5001 => 1, 5002 => 2, 5003 => 3] + array_map(static fn (int $rank): int => $rank + 3, $this->ranks(897));

        $report = RankingReport::compare($ranks, $previous, 900, 1000);

        $this->assertSame([
            'Ranked coasters: 1000 → 900 (-10.0%)',
            '103 coasters leave the ranking',
            '3 newcomers in the top 10',
            'Comparisons: 1000 → 900',
        ], $report->anomalies);
    }

    public function testManyBigMovesAreAnAnomaly(): void
    {
        $previous = $this->ranks(1000);
        // The top 100 upside down
        $ranks = $previous;
        foreach (range(1, 100) as $coaster) {
            $ranks[$coaster] = 101 - $coaster;
        }

        $report = RankingReport::compare($ranks, $previous, 0, 0);

        $this->assertGreaterThan(RankingReport::MAX_BIG_MOVES, \count($report->bigMoves));
        $this->assertSame(\sprintf('%d big moves', \count($report->bigMoves)), $report->anomalies[0]);
    }

    public function testTheFirstRankingHasNothingToCompareWith(): void
    {
        $report = RankingReport::compare($this->ranks(100), [], 1000, 0);

        $this->assertSame([], $report->anomalies);
        $this->assertCount(100, $report->entered);
    }
}
