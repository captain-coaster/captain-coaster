<?php

declare(strict_types=1);

namespace App\Tests\Service\Ranking;

use App\Service\Ranking\RankingDiscord;
use App\Service\Ranking\RankingReport;
use PHPUnit\Framework\TestCase;

class RankingDiscordTest extends TestCase
{
    public function testSummaryGivesTheTop10AndTheRanksOfNewcomersLeaversAndBigMoves(): void
    {
        $report = new RankingReport(100, 101, [7 => 2, 8 => 80], [9 => 95], [[5, 40, 70]], [[1, 1, 1], [7, 2, null], [3, 3, 2], [4, 4, 6]], ['3 newcomers in the top 10']);

        $this->assertSame([
            '**Top 10** ⚡ changed',
            '1. #1 =',
            '2. Seven - Park 🆕',
            '3. #3 ↓1',
            '4. #4 ↑2',
            '',
            'Ranked coasters: 100 → 101',
            'New: #2 Seven - Park, #80 Eight - Park',
            'Left: Nine - Park (was #95)',
            'Big move: Five - Park #40 → #70',
            '⚠️ 3 newcomers in the top 10',
        ], RankingDiscord::summary($report, [5 => 'Five - Park', 7 => 'Seven - Park', 8 => 'Eight - Park', 9 => 'Nine - Park']));
    }

    public function testSummarySaysWhenTheTop10IsUnchanged(): void
    {
        $report = new RankingReport(10, 10, [], [], [], [[1, 1, 1], [2, 2, 2]], []);

        $this->assertSame('**Top 10** unchanged', RankingDiscord::summary($report, [])[0]);
    }
}
