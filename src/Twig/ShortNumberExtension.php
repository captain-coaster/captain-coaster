<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension to convert large positive numbers in to short form like 1K+, 100K+, 199K+, 1M+, 10M+, 1B+ etc
 */
class ShortNumberExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter(
            'shortNum',
            fn($n, int $precision = 1): string => $this->formatNumber($n, $precision)
        )];
    }

    /**
     * Use to convert large positive numbers in to short form like 1K+, 100K+, 199K+, 1M+, 10M+, 1B+ etc
     */
    public function formatNumber($n, int $precision = 1): string
    {
        if ($n >= 0 && $n < 1000) {
            // 1 - 999
            return number_format($n, $precision);
        } elseif ($n < 1_000_000) {
            // 1k-999k
            return number_format($n / 1000, $precision) . 'K';
        } elseif ($n < 1_000_000_000) {
            // 1m-999m
            return number_format($n / 1_000_000, $precision) . 'M';
        } elseif ($n < 1_000_000_000_000) {
            // 1b-999b
            return number_format($n / 1_000_000_000, $precision) . 'B';
        } elseif ($n >= 1_000_000_000_000) {
            // 1t+
            return number_format($n / 1_000_000_000_000, $precision) . 'T';
        } else {
            return '0';
        }
    }
}
