<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\NotificationService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class NotificationController
 * @package App\Controller
 * @Route("/notifications")
 */
class NotificationController extends AbstractController
{
    /**
     * Read a notification
     * @param Notification $notification
     * @param NotificationService $notifService
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @Route("/{id}/read", name="notification_read", methods={"GET"})
     */
    public function readAction(Notification $notification, NotificationService $notifService)
    {
        $redirectUrl = $notifService->getRedirectUrl($notification);

        $em = $this->getDoctrine()->getManager();
        $notification->setIsRead(true);
        $em->persist($notification);
        $em->flush();

        return $this->redirect($redirectUrl);
    }
}
