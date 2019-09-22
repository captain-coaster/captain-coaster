<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ShortNumberExtension extends AbstractExtension
{
    public function getFilters()
    {
        return array(
            new TwigFilter('shortNum', array($this, 'formatNumber')),
        );
    }

    /**
     * Use to convert large positive numbers in to short form like 1K+, 100K+, 199K+, 1M+, 10M+, 1B+ etc
     *
     * @param $n
     * @param int $precision
     * @return float
     */
    public function formatNumber($n, $precision = 1)
    {
        if ($n >= 0 && $n < 1000) {
            // 1 - 999
            return floatval(number_format($n, $precision));
        } elseif ($n < 1000000) {
            // 1k-999k
            return floatval(number_format($n / 1000, $precision)).'K';
        } elseif ($n < 1000000000) {
            // 1m-999m
            return floatval(number_format($n / 1000000, $precision)).'M';
        } elseif ($n < 1000000000000) {
            // 1b-999b
            return floatval(number_format($n / 1000000000, $precision)).'B';
        } elseif ($n >= 1000000000000) {
            // 1t+
            return floatval(number_format($n / 1000000000000, $precision)).'T';
        } else {
            return 0;
        }
    }
}
