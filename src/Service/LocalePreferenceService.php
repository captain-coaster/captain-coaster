<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves which locale an anonymous visitor should be redirected to, and
 * validates redirect targets for the locale-switch action.
 *
 * Priority (see DefaultController::root()): a logged-in user's saved
 * preferredLocale always wins outright — this service only ever runs for
 * anonymous visitors, implementing the "cookie, else browser guess" half
 * of that chain. The cookie is set only via the explicit locale_switch
 * action (never passively refreshed on page views) and is consulted in
 * exactly this one place — no other route reads it, so a locale-prefixed
 * URL always renders that locale regardless of cookie state.
 */
class LocalePreferenceService
{
    public const COOKIE_NAME = 'locale';

    /** @param array<string> $locales */
    public function __construct(
        #[Autowire(param: 'app_locales_array')]
        private readonly array $locales,
    ) {
    }

    public function resolveAnonymousLocale(Request $request): string
    {
        $cookieLocale = $request->cookies->get(self::COOKIE_NAME);
        if (\is_string($cookieLocale) && \in_array($cookieLocale, $this->locales, true)) {
            return $cookieLocale;
        }

        return $request->getPreferredLanguage($this->locales);
    }

    /**
     * Guards the locale_switch action's redirect target against open
     * redirects: only a relative, same-origin path prefixed with the exact
     * locale being switched to is accepted — never an absolute URL, a
     * protocol-relative one, or a path for a *different* locale than the
     * one this redirect is switching to.
     */
    public function isSafeRedirectPath(?string $path, string $locale): bool
    {
        if (null === $path || '' === $path) {
            return false;
        }

        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }

        if (str_contains($path, '/../') || str_ends_with($path, '/..')) {
            return false;
        }

        return '/'.$locale === $path
            || str_starts_with($path, '/'.$locale.'/')
            || str_starts_with($path, '/'.$locale.'?');
    }
}
