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
 * majority language in fully metric countries (Canada, Australia); see
 * GitHub issue #108. The UK is a deliberate exception: despite officially
 * adopting metric, road distances and speed limits stay legally imperial
 * (miles, mph) there — exactly the units this service converts — so
 * en_GB is grouped with en_US rather than with the fully metric English
 * regions.
 *
 * Exposed to Twig via the `units` global (config/packages/twig.yaml),
 * called directly as an object (units.metersOrFeet(...)), not as
 * registered Twig functions/filters.
 */
class UnitsService
{
    /** Browser locale regions that default to imperial when no user preference exists. */
    private const IMPERIAL_LANGUAGE_REGIONS = ['en_US', 'en_GB'];

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
     * The top-preferred browser language decides, with one disambiguation
     * step: a bare "en" (no region) is genuinely ambiguous -- English is
     * the majority language in both imperial (US, UK) and metric (Canada,
     * Australia...) countries -- so when it's the top choice, the next
     * language in preference order is consulted too. A region already
     * present in top position (imperial or not) is never second-guessed;
     * only a bare, region-less "en" falls through.
     */
    private function guessImperialFromBrowserLocale(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $languages = $request->getLanguages();
        $topLanguage = $languages[0] ?? null;

        if (\in_array($topLanguage, self::IMPERIAL_LANGUAGE_REGIONS, true)) {
            return true;
        }

        return 'en' === $topLanguage && \in_array($languages[1] ?? null, self::IMPERIAL_LANGUAGE_REGIONS, true);
    }
}
