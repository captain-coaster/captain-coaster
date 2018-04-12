<?php

namespace BddBundle\Service;

use BddBundle\Entity\Notification;
use BddBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

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
     * NotificationService constructor
     *
     * @param EntityManagerInterface $em
     * @param RouterInterface $router
     */
    public function __construct(EntityManagerInterface $em, RouterInterface $router)
    {
        $this->em = $em;
        $this->router = $router;
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

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * @param Notification $notif
     * @return string
     */
    public function getRedirectUrl(Notification $notif): string
    {
        if ($notif->getType() == self::NOTIF_BADGE) {
            return $this->router->generate('me');
        } elseif ($notif->getType() == self::NOTIF_RANKING) {
            return $this->router->generate('coaster_ranking');
        }

        return $this->router->generate('root');
    }

    /**
     * @param string $message
     * @param string $type
     * @param bool $markSameTypeRead
     */
    public function sendMass(string $message, string $type, bool $markSameTypeRead = true)
    {
        if ($markSameTypeRead) {
            $this->em->getRepository('BddBundle:Notification')->markTypeAsRead($type);
        }

        $users = $this->em->getRepository('BddBundle:User')->findAll();
        foreach ($users as $user) {
            $this->send($user, $message, null, $type);
        }
    }
}
