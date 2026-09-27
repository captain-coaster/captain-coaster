<?php

declare(strict_types=1);

namespace App\Twig\Components\Ranking;

use App\Twig\ShortNumberExtension;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Growth of the ranking's monthly totals: one line per metric, drawn in a
 * 1000×300 SVG box, with a rounded axis maximum and ticks.
 */
#[AsTwigComponent]
final class TrendChart
{
    public const array METRICS = ['ratingNumber', 'userNumber', 'rankedCoasterNumber', 'comparisonNumber'];
    public const int WIDTH = 1000;
    public const int HEIGHT = 300;

    /** @var list<array{month: \DateTimeImmutable, ratingNumber: int, userNumber: int, rankedCoasterNumber: int, comparisonNumber: int}> */
    public array $history = [];

    public function __construct(private readonly ShortNumberExtension $shortNumber)
    {
    }

    /** @return array<string, array{ticks: list<string>, points: string, values: list<int>}> */
    public function getSeries(): array
    {
        $count = \count($this->history);
        $series = [];

        foreach (self::METRICS as $metric) {
            $values = array_map(static fn (array $row): int => (int) $row[$metric], $this->history);
            $max = self::niceMax(max([1, ...$values]));
            $points = [];
            foreach ($values as $i => $value) {
                $x = $count > 1 ? $i / ($count - 1) * self::WIDTH : 0;
                $y = self::HEIGHT - $value / $max * self::HEIGHT;
                $points[] = round($x, 1).','.round($y, 1);
            }

            $series[$metric] = [
                // Top to bottom
                'ticks' => array_map($this->tickLabel(...), [$max, $max / 2, 0]),
                'points' => implode(' ', $points),
                'values' => $values,
            ];
        }

        return $series;
    }

    /** @return list<array{year: string, x: float}> January of each year, as a % of the width */
    public function getYears(): array
    {
        $count = \count($this->history);
        $years = [];
        foreach ($this->history as $i => $row) {
            if ('01' === $row['month']->format('m') && $count > 1) {
                $years[] = ['year' => $row['month']->format('Y'), 'x' => round($i / ($count - 1) * 100, 2)];
            }
        }

        return $years;
    }

    /** "500K" rather than "500.0K", but "2.5K" for 2500. */
    private function tickLabel(int|float $value): string
    {
        $unit = $value >= 1_000_000 ? 1_000_000 : ($value >= 1000 ? 1000 : 1);

        return $this->shortNumber->formatNumber($value, 0.0 === fmod($value, $unit) ? 0 : 1);
    }

    /** Rounds up to 1, 2 or 5 times a power of ten. */
    public static function niceMax(int $value): int
    {
        $magnitude = 10 ** (int) floor(log10($value));
        foreach ([1, 2, 5, 10] as $step) {
            if ($value <= $step * $magnitude) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }
}
