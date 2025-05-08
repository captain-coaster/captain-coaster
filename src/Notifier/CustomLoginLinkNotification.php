<?php

declare(strict_types=1);

namespace App\Notifier;

use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

class CustomLoginLinkNotification extends LoginLinkNotification
{
    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
    {
        $emailMessage = parent::asEmailMessage($recipient, $transport);

        /**
         * get the NotificationEmail object and override the template.
         *
         * @var NotificationEmail $email
         */
        $email = $emailMessage->getMessage();
        $email->htmlTemplate('connect/login_email.html.twig');

        return $emailMessage;
    }
}
