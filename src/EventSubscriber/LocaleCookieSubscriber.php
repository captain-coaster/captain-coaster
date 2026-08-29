<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\LocalePreferenceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Persists the visitor's locale choice: any request carrying a ?setLocale
 * query param (set by the navbar switcher link) gets the locale cookie
 * written on its response. setLocale is a presence flag, not a value --
 * the cookie value comes from the request's already-resolved locale (the
 * URL's _locale segment), so there's no separate value that could ever
 * disagree with the page actually being viewed. No redirect is involved
 * either -- the switcher links directly to the destination page, so
 * there is no redirect target to validate.
 */
class LocaleCookieSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->query->has('setLocale')) {
            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(
            name: LocalePreferenceService::COOKIE_NAME,
            value: $request->getLocale(),
            expire: strtotime('+1 year'),
            path: '/',
            // Hardcoded true, matching security.yaml's remember-me cookie
            // convention -- $request->isSecure() would return false behind
            // a TLS-terminating proxy unless trusted_proxies is configured,
            // which it isn't in any committed config here.
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));
    }
}
