<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the visitor's metric/imperial preference and converts
 * height/speed/distance accordingly.
 *
 * Precedence: a logged-in user's saved profile preference, then a guess
 * from the browser's Accept-Language preferences, walked in order for the
 * first entry that carries a *region* (e.g. "en-US" vs "en-GB") — language
 * alone isn't a reliable signal, since English is also the majority
 * language in fully metric countries (Canada, Australia); see GitHub
 * issue #108. The UK is a deliberate exception: despite officially
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
    /** ISO 3166-1 alpha-2 country codes (from Cloudflare's CF-IPCountry header) that use imperial units. */
    private const IMPERIAL_COUNTRIES = ['US', 'GB'];

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
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return 'imperial' === $user->getPreferredUnits();
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

    public function guessUnitsFromRequest(Request $request): string
    {
        $cfCountry = $request->headers->get('CF-IPCountry');
        if (\is_string($cfCountry)) {
            return \in_array(strtoupper($cfCountry), self::IMPERIAL_COUNTRIES, true) ? 'imperial' : 'metric';
        }

        foreach ($request->getLanguages() as $language) {
            if (str_contains($language, '_')) {
                return \in_array($language, self::IMPERIAL_LANGUAGE_REGIONS, true) ? 'imperial' : 'metric';
            }
        }

        return 'metric';
    }

    /**
     * Walks the browser's language preferences in order and stops at the
     * first one that carries a region -- that region decides, imperial or
     * not. Bare, region-less languages (e.g. plain "en") are skipped: they
     * can't disambiguate anything on their own, since English is the
     * majority language in both imperial (US, UK) and metric (Canada,
     * Australia...) countries. No region found anywhere defaults to
     * metric. Request::getLanguages() parses and caches the
     * Accept-Language header once per request, and the list is at most a
     * handful of entries, so this is a negligible cost to pay on every
     * request.
     */
    private function guessImperialFromBrowserLocale(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        return 'imperial' === $this->guessUnitsFromRequest($request);
    }
}
