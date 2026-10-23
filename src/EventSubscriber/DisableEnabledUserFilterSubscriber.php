<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Doctrine\EnabledUserFilter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The admin sees disabled members' content: moderation judges it, and a ban can be lifted.
 */
class DisableEnabledUserFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Before the firewall and the controllers load any entity
        return [KernelEvents::REQUEST => ['onKernelRequest', 64]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!$event->isMainRequest() || ('/team' !== $path && !str_starts_with($path, '/team/'))) {
            return;
        }

        $filters = $this->em->getFilters();
        if ($filters->isEnabled(EnabledUserFilter::NAME)) {
            $filters->disable(EnabledUserFilter::NAME);
        }
    }
}
