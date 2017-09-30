<?php

namespace BddBundle\Service;

use BddBundle\Entity\Notification;
use BddBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Router;

/**
 * Class NotificationService
 * @package BddBundle\Service
 */
class NotificationService
{
    CONST NOTIF_BADGE = 'badge';
    CONST NOTIF_RANKING = 'ranking';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Router
     */
    private $router;

    /**
     * NotificationService constructor
     *
     * @param EntityManager $em
     * @param Router $router
     */
    public function __construct(EntityManager $em, Router $router)
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
    public function send(User $user, string $message, string $parameter, string $type)
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setMessage($message);
        $notification->setParameter($parameter);
        $notification->setType($type);

        $this->em->persist($notification);
        $this->em->flush();
    }

    public function getRedirectUrl(Notification $notif)
    {
        if ($notif->getType() == self::NOTIF_BADGE) {
            return $this->router->generate('me');
        } elseif ($notif->getType() == self::NOTIF_RANKING) {
            return $this->router->generate('coaster_ranking');
        }

        return $this->router->generate('root');
    }
}