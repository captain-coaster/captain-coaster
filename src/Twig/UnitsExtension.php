<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UnitsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_imperial', [$this, 'isImperial']),
            new TwigFunction('m_or_f', [$this, 'm_or_f']),
            new TwigFunction('kph_or_mph', [$this, 'kph_or_mph']),
            new TwigFunction('km_or_mi', [$this, 'km_or_mi']),
        ];
    }

    public function isImperial(string $locale): bool
    {
        // TODO cookies
        // TODO User

        return 'en' === $locale;
    }

    public function m_or_f(bool $isImperial, int $value): string
    {
        if ($isImperial) {
            return round($value * 3.281).' ft';
        }

        return $value.' m';
    }

    public function kph_or_mph(bool $isImperial, int $value): string
    {
        if ($isImperial) {
            return round($value / 1.609).' mph';
        }

        return $value.' km/h';
    }

    public function km_or_mi(bool $isImperial, int $value): string
    {
        if ($isImperial) {
            return round($value / 1.609).' mi';
        }

        return $value.' km';
    }
}
