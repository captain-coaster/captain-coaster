<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Message\SendNotificationEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationService
{
    /** Rows per flush when fanning out to many users, to keep memory bounded. */
    private const int BATCH_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function send(User $user, NotificationType $type, string $message, ?string $parameter = null): void
    {
        $notification = $this->createNotification($type, $message, $parameter);
        $recipient = $this->addRecipient($notification, $notification->getCreatedAt(), $user);
        $this->em->flush();

        $this->dispatchEmailIfEnabled($recipient, $user, $type);
    }

    /**
     * Sends to every user in $users, in memory-safe batches (used for
     * broadcast-style notifications like ranking updates). Callers pass an
     * iterable (typically a Doctrine generator, e.g. UserRepository::findAllIterable())
     * rather than this service deciding what "everyone" means. References the
     * shared Notification content row by id (not the object itself) once the
     * identity map has been cleared, to avoid re-loading it on every recipient.
     *
     * @param iterable<int, User> $users
     */
    public function sendToUsers(iterable $users, NotificationType $type, string $message, ?string $parameter = null): void
    {
        $notification = $this->createNotification($type, $message, $parameter);
        $notificationId = $notification->getId();
        $createdAt = $notification->getCreatedAt();

        /** @var list<NotificationRecipient> $pendingEmails */
        $pendingEmails = [];
        $count = 0;

        foreach ($users as $user) {
            $notificationRef = $this->em->getReference(Notification::class, $notificationId);
            $recipient = $this->addRecipient($notificationRef, $createdAt, $user);

            if ($user->isEmailNotification() && $type->emailByDefault()) {
                $pendingEmails[] = $recipient;
            }

            if (0 === ++$count % self::BATCH_SIZE) {
                $this->flushAndDispatchEmails($pendingEmails);
            }
        }

        $this->flushAndDispatchEmails($pendingEmails);
    }

    public function markRead(NotificationRecipient $recipient): void
    {
        $recipient->markRead();
        $this->em->flush();
    }

    /** Where to redirect when a notification is clicked. */
    public function getRedirectUrl(NotificationRecipient $recipient): string
    {
        return $this->router->generate($recipient->getNotification()->getType()->route());
    }

    private function createNotification(NotificationType $type, string $message, ?string $parameter): Notification
    {
        $notification = new Notification();
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setParameter($parameter);

        $this->em->persist($notification);
        // Flushed immediately so Gedmo Timestampable populates createdAt before
        // recipient rows copy it.
        $this->em->flush();

        return $notification;
    }

    private function addRecipient(Notification $notification, \DateTimeInterface $createdAt, User $user): NotificationRecipient
    {
        $recipient = new NotificationRecipient();
        $recipient->setNotification($notification);
        $recipient->setCreatedAt($createdAt);
        $recipient->setUser($user);

        $this->em->persist($recipient);

        return $recipient;
    }

    private function dispatchEmailIfEnabled(NotificationRecipient $recipient, User $user, NotificationType $type): void
    {
        if ($user->isEmailNotification() && $type->emailByDefault()) {
            $this->messageBus->dispatch(new SendNotificationEmailMessage($recipient->getId()));
        }
    }

    /** @param list<NotificationRecipient> $pendingEmails */
    private function flushAndDispatchEmails(array &$pendingEmails): void
    {
        $this->em->flush();

        foreach ($pendingEmails as $recipient) {
            $this->messageBus->dispatch(new SendNotificationEmailMessage($recipient->getId()));
        }

        $pendingEmails = [];
        $this->em->clear();
    }
}
