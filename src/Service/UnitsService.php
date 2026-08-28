<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the visitor's metric/imperial preference and converts
 * height/speed/distance accordingly.
 *
 * Precedence: a logged-in user's saved profile preference, then a guess
 * from the browser's Accept-Language *region* (e.g. "en-US" vs "en-GB") —
 * language alone isn't a reliable signal, since English is also the
 * majority language in metric countries (UK, Canada, Australia...); see
 * GitHub issue #108.
 *
 * Exposed to Twig via the `units` global (config/packages/twig.yaml),
 * called directly as an object (units.metersOrFeet(...)), not as
 * registered Twig functions/filters.
 */
class UnitsService
{
    /** Browser locale regions that default to imperial when no user preference exists. */
    private const IMPERIAL_LANGUAGE_REGIONS = ['en_US'];

    private const FEET_PER_METER = 3.281;
    private const KM_PER_MILE = 1.609;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isImperial(): bool
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user) {
            return $user->isImperial();
        }

        return $this->guessImperialFromBrowserLocale();
    }

    public function metersOrFeet(int $value): string
    {
        return $this->isImperial()
            ? $this->metersToFeet($value).' ft'
            : $value.' m';
    }

    public function kphOrMph(int $value): string
    {
        return $this->isImperial()
            ? $this->kphToMph($value).' mph'
            : $value.' km/h';
    }

    public function kmOrMi(int $value): string
    {
        return $this->isImperial()
            ? $this->kmToMiles($value).' mi'
            : $value.' km';
    }

    public function metersToFeet(int $meters): int
    {
        return (int) round($meters * self::FEET_PER_METER);
    }

    public function kphToMph(int $kph): int
    {
        return (int) round($kph / self::KM_PER_MILE);
    }

    public function kmToMiles(int $km): int
    {
        return (int) round($km / self::KM_PER_MILE);
    }

    /**
     * Only the top-preferred browser language is checked, and only an
     * explicit US region tag guesses imperial — a bare "en" with no
     * region, or any non-US region, defaults to metric.
     */
    private function guessImperialFromBrowserLocale(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $topLanguage = $request->getLanguages()[0] ?? null;

        return \in_array($topLanguage, self::IMPERIAL_LANGUAGE_REGIONS, true);
    }
}
