<?php

declare(strict_types=1);

namespace App\Service\Ranking;

/**
 * The ranking algorithm, without any I/O: feed it every rider's ratings and main Top, then compute().
 *
 * Each rider compares every pair of coasters they rated (the better rating wins, same rating is a tie) and every
 * pair in their Top (the better position wins), the Top settling the pairs it holds. A duel is the result of all
 * riders' comparisons of one pair, valid from MIN_COMPARISONS riders: the coaster with more wins scores 100, a draw
 * 50 each. A coaster's score is its mean over its valid duels, ranked from MIN_DUELS valid duels (MIN_DUELS_ELITE
 * above ELITE_SCORE). Coasters short of MIN_DUELS are dropped until none is left, since their duels would weigh on
 * the others.
 */
final class RankingCalculator
{
    final public const int MIN_COMPARISONS = 4;
    final public const int MIN_DUELS = 400;
    final public const int ELITE_SCORE = 95;
    final public const int MIN_DUELS_ELITE = 650;
    // Riders who compared the pair, for the duel shown on the learn-more page
    final public const int FEATURED_DUEL_MIN_RIDERS = 30;

    // Pairs are stored once, as a single int each, to fit a few million of them in memory:
    // key = low index << KEY_SHIFT | high index, value = comparisons << SHIFT | twice the low one's wins (a tie
    // counts 1). PHP buckets int keys by their low bits: KEY_SHIFT must stay narrow, or keys sharing a high index
    // share buckets and every lookup walks a long chain.
    private const int KEY_SHIFT = 14;
    private const int KEY_MASK = (1 << self::KEY_SHIFT) - 1;
    private const int SHIFT = 20;
    private const int MASK = (1 << self::SHIFT) - 1;
    private const int ONE = 1 << self::SHIFT;

    /** @var array<int, int> coaster id => dense index */
    private array $index = [];

    /** @var list<int> dense index => coaster id */
    private array $ids = [];

    /** @var array<int, int> */
    private array $pairs = [];

    /** @var array<int, int> dense index => riders who compared it */
    private array $riders = [];

    private int $contributors = 0;

    /**
     * @param array<int, float> $ratings coaster id => rating
     * @param array<int, int>   $top     coaster id => position in the rider's main Top
     */
    public function addRider(array $ratings, array $top): void
    {
        if (\count($ratings + $top) < 2) {
            return;
        }

        ++$this->contributors;
        foreach ($ratings + $top as $id => $unused) {
            $i = $this->index($id);
            $this->riders[$i] = ($this->riders[$i] ?? 0) + 1;
        }

        // Positions are compared negated, so that the higher value wins in both cases
        $this->compare(array_map(static fn (int $position): int => -$position, $top), [], false);
        $this->compare($ratings, $top, true);
    }

    /**
     * Every pair of $values, the higher value winning; pairs of two $settled coasters are skipped.
     *
     * @param array<int, int|float> $values
     * @param array<int, int>       $settled
     */
    private function compare(array $values, array $settled, bool $countTies): void
    {
        // In index order: $a < $b in every pair below
        $byIndex = [];
        $settledIndexes = [];
        foreach ($values as $id => $value) {
            $i = $this->index($id);
            $byIndex[$i] = $value;
            if (isset($settled[$id])) {
                $settledIndexes[$i] = true;
            }
        }
        ksort($byIndex);
        $indexes = array_keys($byIndex);
        $scores = array_values($byIndex);

        $pairs = &$this->pairs;
        $count = \count($indexes);
        for ($x = 0; $x < $count; ++$x) {
            $a = $indexes[$x];
            $scoreA = $scores[$x];
            $settledA = isset($settledIndexes[$a]);
            $low = $a << self::KEY_SHIFT;

            for ($y = $x + 1; $y < $count; ++$y) {
                $b = $indexes[$y];
                if ($settledA && isset($settledIndexes[$b])) {
                    continue;
                }

                // $a < $b: $a is the low one of the pair
                $scoreB = $scores[$y];
                if ($scoreA > $scoreB) {
                    $add = self::ONE + 2;
                } elseif ($scoreA < $scoreB) {
                    $add = self::ONE;
                } elseif ($countTies) {
                    $add = self::ONE + 1;
                } else {
                    continue;
                }

                $key = $low | $b;
                if (isset($pairs[$key])) {
                    $pairs[$key] += $add;
                } else {
                    $pairs[$key] = $add;
                }
            }
        }
    }

    public function compute(): RankingResult
    {
        // Valid duels, as parallel lists: both coasters, the low one's points (0, 50 or 100), riders
        $low = $high = $points = $comparisons = [];
        foreach ($this->pairs as $key => $value) {
            $count = $value >> self::SHIFT;
            if ($count < self::MIN_COMPARISONS) {
                continue;
            }

            $wins = $value & self::MASK;
            $otherWins = 2 * $count - $wins;
            $low[] = $key >> self::KEY_SHIFT;
            $high[] = $key & self::KEY_MASK;
            $points[] = $wins === $otherWins ? 50 : ($wins > $otherWins ? 100 : 0);
            $comparisons[] = $count;
        }
        $duelCount = \count($low);

        // Drop the coasters short of MIN_DUELS until none is left
        $dropped = [];
        do {
            $duels = array_fill_keys(array_keys($this->riders), 0);
            for ($d = 0; $d < $duelCount; ++$d) {
                if (!isset($dropped[$low[$d]]) && !isset($dropped[$high[$d]])) {
                    ++$duels[$low[$d]];
                    ++$duels[$high[$d]];
                }
            }

            $newlyDropped = 0;
            foreach ($duels as $i => $n) {
                if ($n < self::MIN_DUELS && !isset($dropped[$i])) {
                    $dropped[$i] = true;
                    ++$newlyDropped;
                }
            }
        } while ($newlyDropped > 0);

        $sum = $won = $lost = $tied = [];
        $totalComparisons = 0;
        for ($d = 0; $d < $duelCount; ++$d) {
            $a = $low[$d];
            $b = $high[$d];
            if (isset($dropped[$a]) || isset($dropped[$b])) {
                continue;
            }

            // Each rider's comparison is counted once per side, as the monthly totals always have been
            $totalComparisons += 2 * $comparisons[$d];
            $sum[$a] = ($sum[$a] ?? 0) + $points[$d];
            $sum[$b] = ($sum[$b] ?? 0) + 100 - $points[$d];
            match ($points[$d]) {
                100 => [$won[$a] = ($won[$a] ?? 0) + 1, $lost[$b] = ($lost[$b] ?? 0) + 1],
                0 => [$lost[$a] = ($lost[$a] ?? 0) + 1, $won[$b] = ($won[$b] ?? 0) + 1],
                default => [$tied[$a] = ($tied[$a] ?? 0) + 1, $tied[$b] = ($tied[$b] ?? 0) + 1],
            };
        }

        $rows = [];
        foreach ($sum as $i => $total) {
            $score = $total / $duels[$i];
            if ($score >= self::ELITE_SCORE && $duels[$i] < self::MIN_DUELS_ELITE) {
                continue;
            }

            $rows[] = new RankedCoaster(
                coaster: $this->ids[$i],
                score: $score,
                duels: $duels[$i],
                won: $won[$i] ?? 0,
                lost: $lost[$i] ?? 0,
                tied: $tied[$i] ?? 0,
                riders: $this->riders[$i],
            );
        }

        // Best score first; an exact tie goes to the coaster with more duels, then to the lower id, so that the
        // same data always gives the same ranking
        usort($rows, static fn (RankedCoaster $a, RankedCoaster $b): int => [$b->score, $b->duels, $a->coaster] <=> [$a->score, $a->duels, $b->coaster]);

        return new RankingResult(
            coasters: $rows,
            comparisons: $totalComparisons,
            contributors: $this->contributors,
            featuredDuel: $this->featuredDuel(array_map(static fn (RankedCoaster $row): int => $row->coaster, $rows)),
        );
    }

    /**
     * A head-to-head to show on the learn-more page, drawn at random each month: a coaster ranked 3–20 against one
     * 10 to 40 places lower, with enough riders, that the better-ranked one wins. It skips #1 vs #2 (a rivalry of
     * its own) and the upsets where the better-ranked coaster loses the duel itself, which need more explaining.
     * Each rider who compared the pair adds 1 to the two sides' sum (1 to the winner, 0.5 each for a tie).
     *
     * @param list<int> $ranking coaster ids, best first
     *
     * @return array{first: int, second: int, comparisons: int, firstWins: float}|null
     */
    private function featuredDuel(array $ranking): ?array
    {
        $candidates = [];

        foreach (\array_slice($ranking, 2, 18) as $index => $first) {
            // $first is ranked $index + 3; its opponents are ranked 10 to 40 places lower
            foreach (\array_slice($ranking, $index + 12, 31) as $second) {
                $duel = $this->duel($first, $second);
                if (null === $duel) {
                    continue;
                }

                [$wins, $comparisons] = $duel;
                if ($comparisons >= self::FEATURED_DUEL_MIN_RIDERS && $wins > $comparisons / 2) {
                    $candidates[] = ['first' => $first, 'second' => $second, 'comparisons' => $comparisons, 'firstWins' => $wins];
                }
            }
        }

        return $candidates ? $candidates[array_rand($candidates)] : null;
    }

    /** @return array{float, int}|null $first's wins (a tie counts half) and the riders who compared the pair */
    private function duel(int $first, int $second): ?array
    {
        $a = $this->index[$first] ?? null;
        $b = $this->index[$second] ?? null;
        if (null === $a || null === $b) {
            return null;
        }

        $value = $this->pairs[min($a, $b) << self::KEY_SHIFT | max($a, $b)] ?? null;
        if (null === $value) {
            return null;
        }

        $count = $value >> self::SHIFT;
        $lowWins = $value & self::MASK;

        return [($a < $b ? $lowWins : 2 * $count - $lowWins) / 2, $count];
    }

    private function index(int $id): int
    {
        if (!isset($this->index[$id])) {
            if (\count($this->ids) > self::KEY_MASK) {
                throw new \OverflowException(\sprintf('More than %d coasters to rank: widen KEY_SHIFT.', self::KEY_MASK + 1));
            }
            $this->index[$id] = \count($this->ids);
            $this->ids[] = $id;
        }

        return $this->index[$id];
    }
}
