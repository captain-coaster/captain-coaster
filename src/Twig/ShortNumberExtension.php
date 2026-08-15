<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension to convert large positive numbers in to short form like 1K+, 100K+, 199K+, 1M+, 10M+, 1B+ etc.
 */
class ShortNumberExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter(
            'shortNum',
            fn ($n, int $precision = 1): string => $this->formatNumber($n, $precision)
        )];
    }

    /**
     * Convert large positive numbers to short form (1K, 100K, 1M, 10M, 1B, 1T).
     * Numbers below 1000 are shown without decimals (e.g. 914, not 914.0).
     * Larger numbers drop trailing zeros: 681K (not 681.0K), 1.2M (kept).
     */
    public function formatNumber(int|float $n, int $precision = 1): string
    {
        if ($n >= 0 && $n < 1000) {
            // 1 - 999: never show decimals for integers
            return number_format((float) $n, 0);
        }

        $unit = '';
        $value = (float) $n;
        if ($n < 1_000_000) {
            $value = $n / 1000;
            $unit = 'K';
        } elseif ($n < 1_000_000_000) {
            $value = $n / 1_000_000;
            $unit = 'M';
        } elseif ($n < 1_000_000_000_000) {
            $value = $n / 1_000_000_000;
            $unit = 'B';
        } elseif ($n >= 1_000_000_000_000) {
            $value = $n / 1_000_000_000_000;
            $unit = 'T';
        }

        $formatted = number_format($value, $precision);

        // Drop trailing zeros after the decimal separator (681.0 → 681, 1.20 → 1.2, 1.00 → 1).
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted.$unit;
    }
}
