<?php

declare(strict_types=1);

namespace App\Service\CaptainScore;

/**
 * Pure math for Captain scores.
 *
 * Two strength variants are produced:
 *
 * 1. `strengthScore` — a count-shaped, quality-weighted "what your set is
 *    worth in coasters" number. Formula:
 *
 *        WC = Σᵢ 100^( α · (sᵢ − s₀) / 100 )
 *
 *    Each coaster contributes a multiplier above or below 1 depending on
 *    its score relative to a baseline. With s₀ tuned to the typical rider
 *    average (≈65), a normal rider gets WC ≈ n; elite coasters push it up,
 *    wacky worms barely move it.
 *
 * 2. `legacyStrengthScore` — the original log-scale formula:
 *
 *        CQS = 100/ln(100) · ln( Σ vᵢ·cᵢ / Σ cᵢ ),  with vᵢ = 100^(sᵢ/100), cᵢ = 100^((2i+1)/(2n))
 *        CSS = CQS + 100/ln(100) · ln(n)
 *
 *    Kept for A/B comparison and possible future surfacing.
 *
 * `qualityScore` (CQS) is the same for both variants — see `computeQuality()`.
 *
 * The class is stateless and DB-agnostic on purpose: the same math is
 * meant to be reused for parks (open coasters, kiddies counted as score 0)
 * and manufacturers (ranked coasters only) when those views are added.
 */
class CaptainScoreCalculator
{
    private const float LOG_BASE = 100.0;

    /** Slope of the per-coaster multiplier curve. 0.5 ≈ 10× spread between worst and best. */
    private const float WC_ALPHA = 0.5;

    /** Score at which one coaster contributes exactly 1. ≈ typical per-user mean of ranked coasters. */
    private const float WC_BASELINE = 65.0;

    /** @param list<float> $scores Coaster scores in [0, 100]. May include 0s. */
    public function compute(array $scores): CaptainScores
    {
        $n = \count($scores);
        if (0 === $n) {
            return new CaptainScores(0, null, null, null);
        }

        // Decreasing order — top coasters carry the highest weight in CQS.
        rsort($scores);

        $qualityScore = $this->computeQuality($scores);
        $strengthScore = $this->computeWeightedCount($scores);
        $legacyStrengthScore = $this->computeLegacyStrength($qualityScore, $n);

        return new CaptainScores($n, $qualityScore, $strengthScore, $legacyStrengthScore);
    }

    /**
     * Captain Quality Score — weighted geometric-style mean that favours
     * the rider's best coasters.
     *
     * @param list<float> $scoresDescending
     */
    private function computeQuality(array $scoresDescending): float
    {
        $n = \count($scoresDescending);
        $sumWeighted = 0.0;
        $sumCoef = 0.0;

        foreach ($scoresDescending as $i => $score) {
            $value = self::LOG_BASE ** ($score / 100.0);
            $coef = self::LOG_BASE ** ((2 * $i + 1) / (2 * $n));

            $sumWeighted += $value * $coef;
            $sumCoef += $coef;
        }

        return $this->fromValue($sumWeighted / $sumCoef);
    }

    /**
     * New count-shaped strength: each coaster contributes a multiplier
     * around 1 depending on its score relative to WC_BASELINE.
     *
     * @param list<float> $scores
     */
    private function computeWeightedCount(array $scores): float
    {
        $sum = 0.0;
        foreach ($scores as $score) {
            $sum += self::LOG_BASE ** (self::WC_ALPHA * ($score - self::WC_BASELINE) / 100.0);
        }

        return $sum;
    }

    /** Original log-scale strength: CSS = CQS + 100·log_100(n). Kept for comparison. */
    private function computeLegacyStrength(float $qualityScore, int $n): float
    {
        return $qualityScore + $this->fromValue((float) $n);
    }

    /** Inverse of value = 100^(score/100): score = 100/ln(100) · ln(value). */
    private function fromValue(float $value): float
    {
        return 100.0 * log($value, self::LOG_BASE);
    }
}
