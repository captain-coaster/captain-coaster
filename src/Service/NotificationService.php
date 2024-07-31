<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class NotificationService
{
    final public const string NOTIF_BADGE = 'badge';
    final public const string NOTIF_RANKING = 'ranking';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly Environment $templating,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository
    ) {
    }

    public function send(User $user, string $message, ?string $parameter = null, ?string $type = null): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setParameter($parameter);
        $notification->setType($type);

        $this->sendNotification($notification);

        if ($user->isEmailNotification()) {
            // disable email temporarily
            // $this->sendEmail($notification);
        }
    }

    /** Where to redirect when a notification is clicked. */
    public function getRedirectUrl(Notification $notif): string
    {
        return match ($notif->getType()) {
            self::NOTIF_BADGE => $this->router->generate('me'),
            self::NOTIF_RANKING => $this->router->generate('ranking_index'),
            default => $this->router->generate('root'),
        };
    }

    /** Send notification to everyone. */
    public function sendAll(string $message, string $type, ?string $parameter = null, bool $markSameTypeRead = true): void
    {
        if ($markSameTypeRead) {
            $this->em->getRepository(Notification::class)->markTypeAsRead($type);
        }

        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $this->send($user, $message, $parameter, $type);
        }
    }

    /** "Send" notification (i.e.: persist it). */
    private function sendNotification(Notification $notification): void
    {
        $this->em->persist($notification);
        $this->em->flush();
    }

    /** Send an email. */
    private function sendEmail(Notification $notification): void
    {
        // @todo temp hack
        if (strpos((string) $notification->getUser()->getEmail(), 'notvalid')) {
            return;
        }

        $subject = $this->translator->trans(
            'notif.email.title',
            [],
            'messages',
            $notification->getUser()->getPreferredLocale()
        );

        $message = (new Email())
            ->to($notification->getUser()->getEmail())
            ->subject($subject)
            ->html(
                $this->templating->render('Notification/email.html.twig', ['notification' => $notification])
            );

        $this->mailer->send($message);
    }
}
