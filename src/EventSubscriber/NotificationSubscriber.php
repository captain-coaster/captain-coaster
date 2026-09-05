<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\NotificationType;
use App\Event\BadgeAwardedEvent;
use App\Event\RankingComputedEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maps domain events to notifications. Adding a future notification type
 * means adding an event (if one doesn't already exist for the trigger), a
 * {@see NotificationType} case, and a handler method here — nothing else in
 * the notification system needs to change.
 */
class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RankingComputedEvent::class => 'onRankingComputed',
            BadgeAwardedEvent::class => 'onBadgeAwarded',
        ];
    }

    public function onRankingComputed(RankingComputedEvent $event): void
    {
        if (null !== $event->highlightedCoasterName) {
            $this->notificationService->sendToAllUsers(NotificationType::Ranking, 'notif.ranking.messageWithNewCoaster', $event->highlightedCoasterName);

            return;
        }

        $this->notificationService->sendToAllUsers(NotificationType::Ranking, 'notif.ranking.message');
    }

    public function onBadgeAwarded(BadgeAwardedEvent $event): void
    {
        $this->notificationService->send($event->user, NotificationType::Badge, 'notif.badge.message', $event->badgeName);
    }
}
