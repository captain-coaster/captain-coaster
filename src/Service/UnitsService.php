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
 * Precedence: the current request's own validated `?setUnits=` query
 * parameter (so the very page carrying the switcher link already reflects
 * the new choice, before UnitsCookieSubscriber has persisted it for future
 * requests) > logged-in user's saved profile preference > cookie (anonymous
 * visitors) > browser's Accept-Language region preferences > metric default.
 * See guessUnitsFromRequest() for the Accept-Language logic.
 *
 * Exposed to Twig via the `units` global (config/packages/twig.yaml),
 * called directly as an object (units.metersOrFeet(...)), not as
 * registered Twig functions/filters.
 */
class UnitsService
{
    public const COOKIE_NAME = 'units';

    /** Allow-listed values for the units preference, shared with UnitsCookieSubscriber. */
    public const VALID_UNITS = ['metric', 'imperial'];

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
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $requestedUnits = $request->query->get('setUnits');
            if (\is_string($requestedUnits) && \in_array($requestedUnits, self::VALID_UNITS, true)) {
                return 'imperial' === $requestedUnits;
            }
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            return 'imperial' === $user->getPreferredUnits();
        }

        if (!$request instanceof Request) {
            return false;
        }

        $cookieUnits = $request->cookies->get(self::COOKIE_NAME);
        if (\is_string($cookieUnits) && \in_array($cookieUnits, self::VALID_UNITS, true)) {
            return 'imperial' === $cookieUnits;
        }

        return 'imperial' === $this->guessUnitsFromRequest($request);
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
     * Guess metric/imperial from the request's Accept-Language preferences.
     *
     * Walks the browser's language preferences in order, stopping at the
     * first entry that carries a *region* (e.g. "en-US" vs "en-GB") —
     * language alone isn't a reliable signal, since English is also the
     * majority language in fully metric countries (Canada, Australia); see
     * GitHub issue #108. The UK is a deliberate exception: despite officially
     * adopting metric, road distances and speed limits stay legally imperial
     * (miles, mph) there — exactly the units this service converts — so
     * en_GB is grouped with en_US rather than with the fully metric English
     * regions. No region found anywhere defaults to 'metric'.
     *
     * Request::getLanguages() parses and caches the Accept-Language header
     * once per request, and the list is at most a handful of entries, so
     * this is a negligible cost to pay on every request.
     */
    public function guessUnitsFromRequest(Request $request): string
    {
        foreach ($request->getLanguages() as $language) {
            if (str_contains($language, '_')) {
                return \in_array($language, self::IMPERIAL_LANGUAGE_REGIONS, true) ? 'imperial' : 'metric';
            }
        }

        return 'metric';
    }
}
