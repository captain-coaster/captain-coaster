<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class NotificationService.
 */
class NotificationService
{
    final public const NOTIF_BADGE = 'badge';
    final public const NOTIF_RANKING = 'ranking';

    /**
     * NotificationService constructor.
     */
    public function __construct(private readonly EntityManagerInterface $em, private readonly RouterInterface $router, private readonly Environment $templating, private readonly MailerInterface $mailer, private readonly TranslatorInterface $translator, private readonly string $emailFrom, private readonly string $emailFromName, private readonly \App\Repository\UserRepository $userRepository)
    {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function send(User $user, string $message, string $parameter = null, string $type = null): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setParameter($parameter);
        $notification->setType($type);

        $this->sendNotification($notification);

        if ($user->isEmailNotification()) {
            $this->sendEmail($notification);
        }
    }

    /**
     * Where to redirect when a notification is clicked.
     */
    public function getRedirectUrl(Notification $notif): string
    {
        return match ($notif->getType()) {
            self::NOTIF_BADGE => $this->router->generate('me'),
            self::NOTIF_RANKING => $this->router->generate('ranking_index'),
            default => $this->router->generate('root'),
        };
    }

    /**
     * Send notification to everyone.
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendAll(string $message, string $type, string $parameter = null, bool $markSameTypeRead = true)
    {
        if ($markSameTypeRead) {
            $this->em->getRepository(Notification::class)->markTypeAsRead($type);
        }

        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $this->send($user, $message, $parameter, $type);
        }
    }

    /**
     * "Send" notification (i.e.: persist it).
     */
    private function sendNotification(Notification $notification): void
    {
        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Send an email.
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError|TransportExceptionInterface
     */
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
            ->from(new Address($this->emailFrom, $this->emailFromName))
            ->to($notification->getUser()->getEmail())
            ->subject($subject)
            ->html($this->templating->render('Notification/email.html.twig', ['notification' => $notification])
            );

        $this->mailer->send($message);
    }
}
