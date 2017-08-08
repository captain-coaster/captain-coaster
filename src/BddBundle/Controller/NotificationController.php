<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * Class NotificationController
 * @package BddBundle\Controller
 * @Route("/notifications")
 */
class NotificationController extends Controller
{
    /**
     * @param Notification $notification
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/{id}/read", name="notification_read")
     * @Method({"GET"})
     */
    public function readAction(Notification $notification)
    {
        $notifService = $this->get('BddBundle\Service\NotificationService');
        $redirectUrl = $notifService->getRedirectUrl($notification);

        $em = $this->getDoctrine()->getManager();
        $notification->setIsRead(true);
        $em->persist($notification);
        $em->flush();

        return $this->redirect($redirectUrl);
    }
}
