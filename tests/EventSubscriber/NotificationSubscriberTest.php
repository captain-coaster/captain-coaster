<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Event\BadgeAwardedEvent;
use App\Event\RankingComputedEvent;
use App\EventSubscriber\NotificationSubscriber;
use App\Service\NotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationSubscriberTest extends TestCase
{
    private NotificationService&MockObject $notificationService;
    private NotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->subscriber = new NotificationSubscriber($this->notificationService);
    }

    public function testSubscribesToBothDomainEvents(): void
    {
        $this->assertSame(
            [
                RankingComputedEvent::class => 'onRankingComputed',
                BadgeAwardedEvent::class => 'onBadgeAwarded',
            ],
            NotificationSubscriber::getSubscribedEvents()
        );
    }

    public function testRankingComputedWithoutHighlightedCoasterUsesTheGenericMessage(): void
    {
        $this->notificationService
            ->expects($this->once())
            ->method('sendToAllUsers')
            ->with(NotificationType::Ranking, 'notif.ranking.message', null);

        $this->subscriber->onRankingComputed(new RankingComputedEvent());
    }

    public function testRankingComputedWithHighlightedCoasterUsesTheCoasterMessage(): void
    {
        $this->notificationService
            ->expects($this->once())
            ->method('sendToAllUsers')
            ->with(NotificationType::Ranking, 'notif.ranking.messageWithNewCoaster', 'Steel Vengeance');

        $this->subscriber->onRankingComputed(new RankingComputedEvent('Steel Vengeance'));
    }

    public function testBadgeAwardedSendsToTheAwardedUser(): void
    {
        $user = new User();

        $this->notificationService
            ->expects($this->once())
            ->method('send')
            ->with($user, NotificationType::Badge, 'notif.badge.message', 'badge.rating1');

        $this->subscriber->onBadgeAwarded(new BadgeAwardedEvent($user, 'badge.rating1'));
    }
}
