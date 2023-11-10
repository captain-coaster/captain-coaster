<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class NotificationController.
 */
#[Route(path: '/notifications')]
class NotificationController extends AbstractController
{
    /**
     * Read a notification.
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
