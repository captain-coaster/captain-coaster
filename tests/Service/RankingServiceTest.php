<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RankingService;
use PHPUnit\Framework\TestCase;

class RankingServiceTest extends TestCase
{
    public function testRiderComparisonsCountTopPairsOnceWhenAlsoRated(): void
    {
        $this->assertSame(45, RankingService::riderComparisons(10, 0, 0));
        // 10 ratings, a Top of 5 of them: the Top's 10 pairs replace 10 rating pairs
        $this->assertSame(45, RankingService::riderComparisons(10, 5, 5));
        // 10 ratings, a Top of 5 unrated coasters: 45 + 10
        $this->assertSame(55, RankingService::riderComparisons(10, 5, 0));
    }

    public function testPublicationIsTheFirstAtNoonUtc(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2026-10-01 12:00', new \DateTimeZone('UTC')),
            RankingService::publicationTime(new \DateTimeImmutable('2026-10-01')),
        );
    }

    public function testNextPublicationIsLaterThisMonthUntilNoonOnTheFirst(): void
    {
        $utc = new \DateTimeZone('UTC');

        $this->assertEquals(new \DateTimeImmutable('2026-10-01 12:00', $utc), RankingService::nextPublication(new \DateTimeImmutable('2026-10-01 11:59', $utc)));
        $this->assertEquals(new \DateTimeImmutable('2026-11-01 12:00', $utc), RankingService::nextPublication(new \DateTimeImmutable('2026-10-01 12:00', $utc)));
        $this->assertEquals(new \DateTimeImmutable('2027-01-01 12:00', $utc), RankingService::nextPublication(new \DateTimeImmutable('2026-12-31 23:00', $utc)));
    }

    public function testNextPublicationIsTheSameInstantInEveryTimeZone(): void
    {
        // 1 October 00:30 in Paris is still 30 September in UTC
        $paris = RankingService::nextPublication(new \DateTimeImmutable('2026-10-01 00:30', new \DateTimeZone('Europe/Paris')));
        $newYork = RankingService::nextPublication(new \DateTimeImmutable('2026-09-30 18:30', new \DateTimeZone('America/New_York')));

        $this->assertEquals(new \DateTimeImmutable('2026-10-01 12:00', new \DateTimeZone('UTC')), $paris);
        $this->assertEquals($paris, $newYork);
    }

    public function testMonthOfIsTheUtcMonth(): void
    {
        // Already 1 October in Paris, still September in UTC
        $this->assertSame('2026-09-01', RankingService::monthOf(new \DateTimeImmutable('2026-10-01 01:00', new \DateTimeZone('Europe/Paris')))->format('Y-m-d'));
        $this->assertSame('2026-10-01', RankingService::monthOf(new \DateTimeImmutable('2026-10-01 00:05', new \DateTimeZone('UTC')))->format('Y-m-d'));
    }

    public function testTargetMonthFollowsTheLastPublishedRanking(): void
    {
        $utc = new \DateTimeZone('UTC');
        $september = new \DateTimeImmutable('2026-09-01');

        // Computed early, on the 1st before or after noon: October in every case
        $this->assertSame('2026-10-01', RankingService::targetMonth($september, new \DateTimeImmutable('2026-09-30 10:00', $utc))->format('Y-m-d'));
        $this->assertSame('2026-10-01', RankingService::targetMonth($september, new \DateTimeImmutable('2026-10-01 03:00', $utc))->format('Y-m-d'));
        $this->assertSame('2026-10-01', RankingService::targetMonth($september, new \DateTimeImmutable('2026-10-01 14:00', $utc))->format('Y-m-d'));
        $this->assertSame('2027-01-01', RankingService::targetMonth(new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2026-12-31 23:00', $utc))->format('Y-m-d'));
    }

    public function testTargetMonthIsNeverBeforeTheCurrentMonth(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 10:00', new \DateTimeZone('UTC'));

        // Months skipped since July: September, not August
        $this->assertSame('2026-09-01', RankingService::targetMonth(new \DateTimeImmutable('2026-07-01'), $now)->format('Y-m-d'));
        $this->assertSame('2026-09-01', RankingService::targetMonth(null, $now)->format('Y-m-d'));
    }

    public function testTargetMonthIgnoresTheLastMonthsTimeZone(): void
    {
        // Midnight on 1 September in Paris is still August in UTC
        $september = new \DateTimeImmutable('2026-09-01', new \DateTimeZone('Europe/Paris'));

        $this->assertSame('2026-10-01', RankingService::targetMonth($september, new \DateTimeImmutable('2026-09-30 10:00', new \DateTimeZone('UTC')))->format('Y-m-d'));
    }
}
