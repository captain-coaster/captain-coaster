<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\UnitsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Persists the visitor's units choice, symmetric to
 * LocaleCookieSubscriber. Unlike locale, no URL segment already encodes
 * the target value, so ?setUnits= carries an explicit value (metric |
 * imperial) instead of being a bare presence flag -- validated against
 * that two-item allow-list before being trusted. Logged in, it writes
 * straight to the profile; anonymous, it sets the units cookie.
 */
class UnitsCookieSubscriber implements EventSubscriberInterface
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

        $units = $event->getRequest()->query->get('setUnits');
        if (!\is_string($units) || !\in_array($units, UnitsService::VALID_UNITS, true)) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User) {
            if ($user->getPreferredUnits() !== $units) {
                $user->setPreferredUnits($units);
                $this->em->flush();
            }

            return;
        }

        $event->getResponse()->headers->setCookie(Cookie::create(
            name: UnitsService::COOKIE_NAME,
            value: $units,
            expire: strtotime('+1 year'),
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));
    }
}
