<?php

declare(strict_types=1);

namespace App\Notifier;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Adds the request (method, path, member id) to the error notifications Monolog sends to Discord.
 * The path only: a query string can carry a login-link signature.
 */
final readonly class RequestContextNotifier implements NotifierInterface
{
    public function __construct(
        private NotifierInterface $notifier,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        $request = $this->requestStack->getMainRequest();
        if (null !== $request) {
            $context = $request->getMethod().' '.$request->getPathInfo();
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $context .= ' · member #'.$user->getId();
            }
            $notification->subject($notification->getSubject()."\n".$context);
        }

        $this->notifier->send($notification, ...$recipients);
    }

    /**
     * Called by NotifierHandler, though it is not on the interface.
     *
     * @return RecipientInterface[]
     */
    public function getAdminRecipients(): array
    {
        return $this->notifier instanceof Notifier ? $this->notifier->getAdminRecipients() : [];
    }
}
