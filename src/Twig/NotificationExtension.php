<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Feeds the navbar's unread-count pill with a bounded query.
 */
class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRecipientRepository $notificationRecipientRepository,
        private readonly Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_notification_count', $this->unreadNotificationCount(...)),
        ];
    }

    public function unreadNotificationCount(): int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->notificationRecipientRepository->countUnreadForUser($user) : 0;
    }
}
