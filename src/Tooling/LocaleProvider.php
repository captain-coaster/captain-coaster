<?php

declare(strict_types=1);

namespace App\Tooling;

use Symfony\Component\HttpFoundation\RequestStack;

class LocaleProvider
{
    public function __construct(private array $locales, private RequestStack $requestStack)
    {
    }

    public function provideCurrentLocale(): string
    {
        return $this->requestStack->getMainRequest()->getDefaultLocale();
    }

    public function getAvailableLocales(): array
    {
        return $this->locales;
    }
}
