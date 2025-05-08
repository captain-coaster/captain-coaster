<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UnitsService extends AbstractExtension
{
    private Security $security;
    private RequestStack $requestStack;

    public function __construct(Security $security, RequestStack $requestStack)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_imperial', [$this, 'isImperial']),
            new TwigFunction('m_or_f', [$this, 'm_or_f']),
            new TwigFunction('kph_or_mph', [$this, 'kph_or_mph']),
            new TwigFunction('km_or_mi', [$this, 'km_or_mi']),
        ];
    }

    public function isImperial(): bool
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user) {
            return $user->isImperial();
        }

        // TODO cookies

        return 'en' === $this->requestStack->getCurrentRequest()->getLocale();
    }

    public function m_or_f(int $value): string
    {
        if ($this->isImperial()) {
            return round($value * 3.281).' ft';
        }

        return $value.' m';
    }

    public function kph_or_mph(int $value): string
    {
        if ($this->isImperial()) {
            return round($value / 1.609).' mph';
        }

        return $value.' km/h';
    }

    public function km_or_mi(int $value): string
    {
        if ($this->isImperial()) {
            return round($value / 1.609).' mi';
        }

        return $value.' km';
    }
}
