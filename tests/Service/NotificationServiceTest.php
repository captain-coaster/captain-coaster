<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Message\SendNotificationEmailMessage;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RouterInterface&MockObject $router;
    private MessageBusInterface&MockObject $messageBus;
    private NotificationService $service;
    private int $nextRecipientId = 1;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new NotificationService($this->em, $this->router, $this->messageBus);

        // Real Doctrine sets Notification::createdAt (Gedmo, on flush) and assigns
        // auto-increment ids on persist; both are stubbed here since flush() is mocked.
        $this->em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof Notification) {
                $this->setPrivateProperty($entity, 'createdAt', new \DateTime());
            }
            if ($entity instanceof NotificationRecipient) {
                $this->setPrivateProperty($entity, 'id', $this->nextRecipientId++);
            }
        });
        $this->em->method('getReference')->willReturn(new Notification());
    }

    public function testSendDispatchesEmailWhenUserOptedInAndTypeDefaultsToEmail(): void
    {
        $user = $this->userWithEmailNotification(true);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (object $message) => $message instanceof SendNotificationEmailMessage && 1 === $message->recipientId))
            ->willReturnCallback(static fn (object $message) => new Envelope($message));

        $this->service->send($user, NotificationType::Badge, 'notif.badge.message', 'badge.rating1');
    }

    public function testSendDoesNotDispatchEmailWhenUserOptedOut(): void
    {
        $user = $this->userWithEmailNotification(false);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->send($user, NotificationType::Badge, 'notif.badge.message', 'badge.rating1');
    }

    public function testSendDoesNotDispatchEmailWhenTypeDoesNotEmailByDefault(): void
    {
        $user = $this->userWithEmailNotification(true);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->send($user, NotificationType::Ranking, 'notif.ranking.message');
    }

    public function testSendToUsersDispatchesOneEmailPerOptedInUser(): void
    {
        $users = [
            $this->userWithEmailNotification(true),
            $this->userWithEmailNotification(false),
            $this->userWithEmailNotification(true),
        ];

        $this->messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message) => new Envelope($message));

        // Badge, not Ranking, since Ranking never emails by default (asserted separately) —
        // this test is about the per-recipient opt-in filter within sendToUsers() itself.
        $this->service->sendToUsers($users, NotificationType::Badge, 'notif.badge.message', 'badge.rating1');
    }

    public function testSendToUsersNeverEmailsForRankingRegardlessOfOptIn(): void
    {
        $users = [$this->userWithEmailNotification(true), $this->userWithEmailNotification(true)];

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->service->sendToUsers($users, NotificationType::Ranking, 'notif.ranking.message');
    }

    public function testSendToUsersFlushesAndClearsAcrossBatches(): void
    {
        $users = array_map(fn () => $this->userWithEmailNotification(false), range(1, 205));

        // 1 flush inside createNotification() + 1 at the 200-row batch boundary + 1 at the end;
        // clear() runs after each of the latter two.
        $this->em->expects($this->exactly(3))->method('flush');
        $this->em->expects($this->exactly(2))->method('clear');

        $this->service->sendToUsers($users, NotificationType::Ranking, 'notif.ranking.message');
    }

    public function testGetRedirectUrlUsesTheNotificationTypesRoute(): void
    {
        $notification = new Notification();
        $notification->setType(NotificationType::Ranking);
        $recipient = new NotificationRecipient();
        $recipient->setNotification($notification);

        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('ranking_index')
            ->willReturn('/en/ranking');

        $this->assertSame('/en/ranking', $this->service->getRedirectUrl($recipient));
    }

    public function testMarkReadFlushesTheChange(): void
    {
        $recipient = new NotificationRecipient();

        $this->em->expects($this->once())->method('flush');

        $this->service->markRead($recipient);

        $this->assertTrue($recipient->isRead());
        $this->assertNotNull($recipient->getReadAt());
    }

    private function userWithEmailNotification(bool $enabled): User
    {
        $user = new User();
        $user->setEmail(uniqid('user', true).'@example.com');
        $user->setEmailNotification($enabled);

        return $user;
    }

    private function setPrivateProperty(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($entity);
        $reflection->getProperty($property)->setValue($entity, $value);
    }
}
