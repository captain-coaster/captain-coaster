<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Ranking;

use App\Twig\Components\Ranking\TrendChart;
use App\Twig\ShortNumberExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrendChartTest extends TestCase
{
    /** @return iterable<array{int, int}> */
    public static function niceMaxProvider(): iterable
    {
        yield [1, 1];
        yield [7, 10];
        yield [100, 100];
        yield [101, 200];
        yield [2044, 5000];
        yield [24_191, 50_000];
        yield [804_561, 1_000_000];
    }

    #[DataProvider('niceMaxProvider')]
    public function testNiceMax(int $value, int $expected): void
    {
        $this->assertSame($expected, TrendChart::niceMax($value));
    }

    public function testSeriesScaleToTheRoundedMaximum(): void
    {
        \Locale::setDefault('en');
        $chart = new TrendChart(new ShortNumberExtension());
        $chart->history = [
            ['computedAt' => new \DateTime('2025-12-01'), 'ratingNumber' => 0, 'userNumber' => 10, 'rankedCoasterNumber' => 100, 'comparisonNumber' => 1000],
            ['computedAt' => new \DateTime('2026-01-01'), 'ratingNumber' => 400_000, 'userNumber' => 20, 'rankedCoasterNumber' => 200, 'comparisonNumber' => 2000],
            ['computedAt' => new \DateTime('2026-02-01'), 'ratingNumber' => 800_000, 'userNumber' => 30, 'rankedCoasterNumber' => 300, 'comparisonNumber' => 3000],
        ];

        $ratings = $chart->getSeries()['ratingNumber'];

        $this->assertSame(1_000_000, $ratings['max']);
        $this->assertSame(['1M', '500K', '0'], $ratings['ticks']);
        $this->assertSame('0,300 500,180 1000,60', $ratings['points']);
        $this->assertSame([['year' => '2026', 'x' => 50.0]], $chart->getYears());
    }
}
