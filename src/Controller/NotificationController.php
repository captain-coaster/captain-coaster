<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\NotificationService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class NotificationController
 * @package App\Controller
 */
#[Route(path: '/notifications')]
class NotificationController extends AbstractController
{
    /**
     * Read a notification
     */
    #[Route(path: '/{id}/read', name: 'notification_read', methods: ['GET'])]
    public function readAction(Notification $notification, NotificationService $notifService): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $redirectUrl = $notifService->getRedirectUrl($notification);

        $em = $this->getDoctrine()->getManager();
        $notification->setIsRead(true);
        $em->persist($notification);
        $em->flush();

        return $this->redirect($redirectUrl);
    }
}
