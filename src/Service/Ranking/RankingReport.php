<?php

declare(strict_types=1);

namespace App\Service\Ranking;

/**
 * What a new ranking changes compared with the published one, and its anomalies.
 *
 * Anomaly thresholds are set well above the largest monthly change seen from 2023 to 2026 (±2% ranked coasters,
 * 44 leaving, 8 big moves, 1 newcomer in the top 10), so that an anomaly always deserves a look.
 */
final readonly class RankingReport
{
    // A big move changes the rank by this factor or more, and by BIG_MOVE_MIN_PLACES: the closer to #1, the fewer
    // places it takes (#30 to #55, #100 to #130, but not #800 to #850)
    final public const float BIG_MOVE_FACTOR = 1.25;
    final public const int BIG_MOVE_MIN_PLACES = 5;

    final public const float MAX_RANKED_CHANGE = 0.05;
    final public const int MAX_LEFT = 50;
    final public const int MAX_BIG_MOVES = 15;
    final public const int MAX_TOP10_NEWCOMERS = 2;
    final public const float MAX_COMPARISONS_DROP = 0.02;

    /**
     * @param array<int, int>                 $entered   coaster id => new rank, best first
     * @param array<int, int>                 $left      coaster id => previous rank, best first
     * @param list<array{int, int, int}>      $bigMoves  coaster id, previous rank, new rank; largest factor first
     * @param list<array{int, int, int|null}> $top10     coaster id, new rank, previous rank (null: new)
     * @param list<string>                    $anomalies
     */
    public function __construct(
        public int $rankedBefore,
        public int $rankedNow,
        public array $entered,
        public array $left,
        public array $bigMoves,
        public array $top10,
        public array $anomalies,
    ) {
    }

    /**
     * @param array<int, int> $ranks         coaster id => new rank
     * @param array<int, int> $previousRanks coaster id => published rank
     */
    public static function compare(array $ranks, array $previousRanks, int $comparisons, int $previousComparisons): self
    {
        $entered = array_diff_key($ranks, $previousRanks);
        asort($entered);
        $left = array_diff_key($previousRanks, $ranks);
        asort($left);

        $bigMoves = [];
        $top10 = [];
        foreach ($ranks as $coaster => $rank) {
            $previous = $previousRanks[$coaster] ?? null;
            if ($rank <= 10) {
                $top10[] = [$coaster, $rank, $previous];
            }
            if (null !== $previous && abs($previous - $rank) >= self::BIG_MOVE_MIN_PLACES && max($previous, $rank) >= self::BIG_MOVE_FACTOR * min($previous, $rank)) {
                $bigMoves[] = [$coaster, $previous, $rank];
            }
        }
        usort($top10, static fn (array $a, array $b): int => $a[1] <=> $b[1]);
        usort($bigMoves, static fn (array $a, array $b): int => max($b[1], $b[2]) / min($b[1], $b[2]) <=> max($a[1], $a[2]) / min($a[1], $a[2]));

        $anomalies = [];
        // The very first ranking has nothing to compare with
        if ([] !== $previousRanks) {
            $change = \count($ranks) / \count($previousRanks) - 1;
            if (abs($change) > self::MAX_RANKED_CHANGE) {
                $anomalies[] = \sprintf('Ranked coasters: %d → %d (%+.1f%%)', \count($previousRanks), \count($ranks), 100 * $change);
            }
            if (\count($left) > self::MAX_LEFT) {
                $anomalies[] = \sprintf('%d coasters leave the ranking', \count($left));
            }
            if (\count($bigMoves) > self::MAX_BIG_MOVES) {
                $anomalies[] = \sprintf('%d big moves', \count($bigMoves));
            }
            $top10Newcomers = \count(array_filter($top10, static fn (array $row): bool => null === $row[2] || $row[2] > 10));
            if ($top10Newcomers > self::MAX_TOP10_NEWCOMERS) {
                $anomalies[] = \sprintf('%d newcomers in the top 10', $top10Newcomers);
            }
        }
        if ($previousComparisons > 0 && $comparisons < (1 - self::MAX_COMPARISONS_DROP) * $previousComparisons) {
            $anomalies[] = \sprintf('Comparisons: %d → %d', $previousComparisons, $comparisons);
        }

        return new self(\count($previousRanks), \count($ranks), $entered, $left, $bigMoves, $top10, $anomalies);
    }

    /** Whether the top 10 differs from the published one, in members or order. */
    public function top10Changed(): bool
    {
        foreach ($this->top10 as [, $rank, $previous]) {
            if ($rank !== $previous) {
                return true;
            }
        }

        return false;
    }

    /** @return array{rankedBefore: int, rankedNow: int, entered: array<int, int>, left: array<int, int>, bigMoves: list<array{int, int, int}>, top10: list<array{int, int, int|null}>, anomalies: list<string>} */
    public function toArray(): array
    {
        return [
            'rankedBefore' => $this->rankedBefore,
            'rankedNow' => $this->rankedNow,
            'entered' => $this->entered,
            'left' => $this->left,
            'bigMoves' => $this->bigMoves,
            'top10' => $this->top10,
            'anomalies' => $this->anomalies,
        ];
    }
}
