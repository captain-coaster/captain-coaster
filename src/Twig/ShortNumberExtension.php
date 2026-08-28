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
     * Use to convert large positive numbers in to short form like 1K+, 100K+, 199K+, 1M+, 10M+, 1B+ etc.
     * $locale defaults to the current request locale (via \Locale::getDefault(), which Symfony keeps in
     * sync) — number_format() was hardcoded to "." regardless of locale, same bug class as #275.
     */
    public function formatNumber(int|float $n, int $precision = 1, ?string $locale = null): string
    {
        $formatter = new \NumberFormatter($locale ?? \Locale::getDefault(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $precision);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $precision);

        if ($n >= 0 && $n < 1000) {
            // 1 - 999
            return $this->format($formatter, $n);
        } elseif ($n < 1_000_000) {
            // 1k-999k
            return $this->format($formatter, $n / 1000).'K';
        } elseif ($n < 1_000_000_000) {
            // 1m-999m
            return $this->format($formatter, $n / 1_000_000).'M';
        } elseif ($n < 1_000_000_000_000) {
            // 1b-999b
            return $this->format($formatter, $n / 1_000_000_000).'B';
        } elseif ($n >= 1_000_000_000_000) {
            // 1t+
            return $this->format($formatter, $n / 1_000_000_000_000).'T';
        }

        return '0';
    }

    private function format(\NumberFormatter $formatter, int|float $value): string
    {
        $formatted = $formatter->format($value);
        if (false === $formatted) {
            throw new \RuntimeException(\sprintf('Failed to format number "%s": %s', $value, $formatter->getErrorMessage()));
        }

        return $formatted;
    }
}
