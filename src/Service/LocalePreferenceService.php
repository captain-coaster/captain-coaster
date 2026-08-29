<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves which locale an anonymous visitor should be redirected to, and
 * validates locale values coming from user input (cookie, query param).
 *
 * Priority (see DefaultController::root()): a logged-in user's saved
 * preferredLocale always wins outright — this service only ever runs for
 * anonymous visitors, implementing the "cookie, else browser guess" half
 * of that chain. The cookie is set only via LocaleCookieSubscriber, in
 * response to an explicit navbar switcher click (?setLocale=...), and is
 * consulted in exactly this one place — no other route reads it, so a
 * locale-prefixed URL always renders that locale regardless of cookie
 * state.
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
        if (\is_string($cookieLocale) && $this->isSupportedLocale($cookieLocale)) {
            return $cookieLocale;
        }

        return $request->getPreferredLanguage($this->locales);
    }

    public function isSupportedLocale(string $locale): bool
    {
        return \in_array($locale, $this->locales, true);
    }
}
