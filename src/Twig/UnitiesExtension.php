<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UnitiesExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_imperial', [$this, 'isImperial']),
        ];
    }

    public function isImperial(string $locale): bool
    {
        // TODO cookies
        // TODO User

        return 'en' === $locale;
    }
}
