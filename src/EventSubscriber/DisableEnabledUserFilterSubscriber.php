<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Doctrine\EnabledUserFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The admin sees disabled members' content: moderation judges it, and a ban can be lifted. Matched on the route
 * name, since admin pages live both at /team and under the locale (/fr/team/moderation).
 */
class DisableEnabledUserFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // After the router (32) has named the route, before the firewall (8) and the controllers load any entity
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $route = (string) $event->getRequest()->attributes->get('_route');
        if (!$event->isMainRequest() || ('admin' !== $route && !str_starts_with($route, 'admin_'))) {
            return;
        }

        $filters = $this->em->getFilters();
        if ($filters->isEnabled(EnabledUserFilter::NAME)) {
            $filters->disable(EnabledUserFilter::NAME);
        }
    }
}
