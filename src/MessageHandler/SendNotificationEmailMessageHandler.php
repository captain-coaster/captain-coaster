<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendNotificationEmailMessage;
use App\Repository\NotificationRecipientRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
final class SendNotificationEmailMessageHandler
{
    public function __construct(
        private readonly NotificationRecipientRepository $notificationRecipientRepository,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly Environment $templating,
    ) {
    }

    public function __invoke(SendNotificationEmailMessage $message): void
    {
        $recipient = $this->notificationRecipientRepository->find($message->recipientId);

        // The user may have opted out, or the row may have been purged, between dispatch and processing.
        if (null === $recipient || !$recipient->getUser()->isEmailNotification()) {
            return;
        }

        $user = $recipient->getUser();
        $subject = $this->translator->trans('notif.email.title', [], 'notification', $user->getPreferredLocale());

        $email = new Email()
            ->to($user->getEmail())
            ->subject($subject)
            ->html($this->templating->render('Notification/email.html.twig', ['recipient' => $recipient]));

        $this->mailer->send($email);
    }
}
