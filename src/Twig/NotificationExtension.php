<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Feeds the navbar notification dropdown with bounded queries.
 *
 * User::getUnreadNotifications() used to filter the notifications collection in
 * PHP, so Doctrine loaded every notification the user ever received — read ones
 * included — on every page render, and they then rode along in the serialized
 * session token.
 */
class NotificationExtension extends AbstractExtension
{
    private const int DROPDOWN_LIMIT = 3;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notifications', $this->unreadNotifications(...)),
            new TwigFunction('unread_notification_count', $this->unreadNotificationCount(...)),
        ];
    }

    /** @return array<int, Notification> */
    public function unreadNotifications(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User
            ? $this->notificationRepository->findUnreadForUser($user, self::DROPDOWN_LIMIT)
            : [];
    }

    public function unreadNotificationCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notificationRepository->countUnreadForUser($user) : 0;
    }
}
