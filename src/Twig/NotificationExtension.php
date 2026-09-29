<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Feeds the navbar's unread-count pills with a bounded query, run once per request: the tab bar and the header both
 * show it.
 */
class NotificationExtension extends AbstractExtension implements ResetInterface
{
    private ?int $unreadCount = null;

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
        if (!$user instanceof User) {
            return 0;
        }

        return $this->unreadCount ??= $this->notificationRecipientRepository->countUnreadForUser($user);
    }

    public function reset(): void
    {
        $this->unreadCount = null;
    }
}
