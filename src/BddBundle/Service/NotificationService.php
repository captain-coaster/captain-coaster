<?php

namespace BddBundle\Service;

use BddBundle\Entity\Notification;
use BddBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Templating\EngineInterface;

/**
 * Class NotificationService
 * @package BddBundle\Service
 */
class NotificationService
{
    CONST NOTIF_BADGE = 'badge';
    CONST NOTIF_RANKING = 'ranking';

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
     * @var string
     */
    private $emailFrom;

    /**
     * NotificationService constructor
     *
     * @param EntityManagerInterface $em
     * @param RouterInterface $router
     * @param EngineInterface $templating
     * @param string $emailFrom
     */
    public function __construct(
        EntityManagerInterface $em,
        RouterInterface $router,
        EngineInterface $templating,
        \Swift_Mailer $mailer,
        string $emailFrom
    ) {
        $this->em = $em;
        $this->router = $router;
        $this->templating = $templating;
        $this->mailer = $mailer;
        $this->emailFrom = $emailFrom;
    }

    /**
     * @param User $user
     * @param string $message
     * @param string $parameter
     * @param string $type
     */
    public function send(User $user, string $message, string $parameter = null, string $type): void
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
                return $this->router->generate('coaster_ranking');
            default:
                return $this->router->generate('root');
        }
    }

    /**
     * Send notification to everyone
     * @param string $message
     * @param string $type
     * @param bool $markSameTypeRead
     */
    public function sendAll(string $message, string $type, bool $markSameTypeRead = true)
    {
        if ($markSameTypeRead) {
            $this->em->getRepository('BddBundle:Notification')->markTypeAsRead($type);
        }

        $users = $this->em->getRepository('BddBundle:User')->findAll();
        foreach ($users as $user) {
            $this->send($user, $message, null, $type);
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
     */
    private function sendEmail(Notification $notification): void
    {
        $message = (new \Swift_Message('New notification'))
            ->setFrom($this->emailFrom)
            ->setTo($notification->getUser()->getEmail())
            ->setBody(
                $this->templating->render(
                    '@Bdd/Notification/email.html.twig',
                    [
                        'notification' => $notification,
                        'url' => $this->getRedirectUrl($notification),
                    ]
                ),
                'text/html'
            );

//        $this->mailer->send($message);
    }
}
