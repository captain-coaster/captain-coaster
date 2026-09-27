<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\Enum\NotificationType;
use App\Event\BadgeAwardedEvent;
use App\Event\RankingPublishedEvent;
use App\EventSubscriber\NotificationSubscriber;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationSubscriberTest extends TestCase
{
    private NotificationService&MockObject $notificationService;
    private UserRepository&MockObject $userRepository;
    private NotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->subscriber = new NotificationSubscriber($this->notificationService, $this->userRepository);
    }

    public function testSubscribesToBothDomainEvents(): void
    {
        $this->assertSame(
            [
                RankingPublishedEvent::class => 'onRankingPublished',
                BadgeAwardedEvent::class => 'onBadgeAwarded',
            ],
            NotificationSubscriber::getSubscribedEvents()
        );
    }

    public function testRankingPublishedWithoutHighlightedCoasterUsesTheGenericMessage(): void
    {
        $users = [new User()];
        $this->userRepository->method('findAllIterable')->willReturn($users);

        $this->notificationService
            ->expects($this->once())
            ->method('sendToUsers')
            ->with($users, NotificationType::Ranking, 'notif.ranking.message', null);

        $this->subscriber->onRankingPublished(new RankingPublishedEvent());
    }

    public function testRepublishedRankingNotifiesNobody(): void
    {
        $this->notificationService->expects($this->never())->method('sendToUsers');

        $this->subscriber->onRankingPublished(new RankingPublishedEvent(republished: true));
    }

    public function testRankingPublishedWithHighlightedCoasterUsesTheCoasterMessage(): void
    {
        $users = [new User()];
        $this->userRepository->method('findAllIterable')->willReturn($users);

        $this->notificationService
            ->expects($this->once())
            ->method('sendToUsers')
            ->with($users, NotificationType::Ranking, 'notif.ranking.messageWithNewCoaster', 'Steel Vengeance');

        $this->subscriber->onRankingPublished(new RankingPublishedEvent('Steel Vengeance'));
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
