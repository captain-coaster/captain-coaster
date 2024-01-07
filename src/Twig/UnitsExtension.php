<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UnitsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_imperial', [$this, 'isImperial']),
            new TwigFunction('meter_or_feet', [$this, 'm_or_f']),
            new TwigFunction('meter_or_feet', [$this, 'kph_or_mph']),
            new TwigFunction('meter_or_feet', [$this, 'km_or_mil']),
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

    public function km_or_mil(bool $isImperial, int $value): string
    {
        if ($isImperial) {
            return round($value / 1.609).' mil';
        }

        return $value.' km';
    }
}
