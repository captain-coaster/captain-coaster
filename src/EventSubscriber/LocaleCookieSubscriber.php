<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\LocalePreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Persists the visitor's locale choice: any request carrying a ?setLocale
 * query param (set by the account-menu language row) applies it. Logged
 * in, the choice writes straight to the profile -- no cookie is ever
 * written or read for an authenticated visitor, in either direction.
 * Anonymous, it sets the locale cookie exactly as before. setLocale is a
 * presence flag, not a value -- the target locale is always the request's
 * own already-resolved locale (the URL's _locale segment), never a
 * separate value that could disagree with the page being viewed.
 */
class LocaleCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

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

        $locale = $request->getLocale();
        $user = $this->security->getUser();

        if ($user instanceof User) {
            if ($user->getPreferredLocale() !== $locale) {
                $user->setPreferredLocale($locale);
                $this->em->flush();
            }

            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(
            name: LocalePreferenceService::COOKIE_NAME,
            value: $locale,
            expire: strtotime('+1 year'),
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));
    }
}
