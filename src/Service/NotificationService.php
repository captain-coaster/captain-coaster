<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class NotificationService
 * @package App\Service
 */
class NotificationService
{
    const NOTIF_BADGE = 'badge';
    const NOTIF_RANKING = 'ranking';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EngineInterface
     */
    private $templating;

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $emailFrom;

    /**
     * @var string
     */
    private $emailFromName;

    /**
     * NotificationService constructor
     *
     * @param EntityManagerInterface $em
     * @param RouterInterface $router
     * @param Environment $templating
     * @param \Swift_Mailer $mailer
     * @param TranslatorInterface $translator
     * @param string $emailFrom
     * @param string $emailFromName
     */
    public function __construct(
        EntityManagerInterface $em,
        RouterInterface $router,
        Environment $templating,
        \Swift_Mailer $mailer,
        TranslatorInterface $translator,
        string $emailFrom,
        string $emailFromName
    )
    {
        $this->em = $em;
        $this->router = $router;
        $this->templating = $templating;
        $this->mailer = $mailer;
        $this->translator = $translator;
        $this->emailFrom = $emailFrom;
        $this->emailFromName = $emailFromName;
    }

    /**
     * @param User $user
     * @param string $message
     * @param string $parameter
     * @param string $type
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
     * Where to redirect when a notification is clicked
     * @param Notification $notif
     * @return string
     */
    public function getRedirectUrl(Notification $notif): string
    {
        switch ($notif->getType()) {
            case self::NOTIF_BADGE:
                return $this->router->generate('me');
            case self::NOTIF_RANKING:
                return $this->router->generate('ranking_index');
            default:
                return $this->router->generate('root');
        }
    }

    /**
     * Send notification to everyone
     * @param string $message
     * @param string $type
     * @param string|null $parameter
     * @param bool $markSameTypeRead
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function sendAll(string $message, string $type, string $parameter = null, bool $markSameTypeRead = true)
    {
        if ($markSameTypeRead) {
            $this->em->getRepository(Notification::class)->markTypeAsRead($type);
        }

        $users = $this->em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $this->send($user, $message, $parameter, $type);
        }
    }

    /**
     * "Send" notification (i.e.: persist it)
     * @param Notification $notification
     */
    private function sendNotification(Notification $notification): void
    {
        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Send an email
     * @param Notification $notification
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function sendEmail(Notification $notification): void
    {
        // @todo temp hack
        if (strpos($notification->getUser()->getEmail(), 'notvalid')) {
            return;
        }

        $subject = $this->translator->trans(
            'notif.email.title',
            [],
            'messages',
            $notification->getUser()->getPreferredLocale()
        );

        $message = (new \Swift_Message($subject))
            ->setFrom([$this->emailFrom => $this->emailFromName])
            ->setTo($notification->getUser()->getEmail())
            ->setBody(
                $this->templating->render(
                    'Notification/email.html.twig',
                    [
                        'notification' => $notification,
                    ]
                ),
                'text/html'
            );

        $this->mailer->send($message);
    }
}
