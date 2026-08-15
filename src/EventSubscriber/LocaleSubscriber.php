<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Remembers the locale the visitor is browsing in (resolved from the URL `_locale`)
 * so the locale-less root redirect can honour it — notably for anonymous visitors,
 * who have no persisted preferred locale to fall back on.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Run after the router (priority 32) has resolved `_locale`.
        return [KernelEvents::REQUEST => [['onKernelRequest', 15]]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->attributes->get('_locale');

        if (\is_string($locale) && $request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }
    }
}
