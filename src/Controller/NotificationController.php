<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/notifications')]
class NotificationController extends AbstractController
{
    /** Read a notification. */
    #[Route(path: '/{id}/read', name: 'notification_read', methods: ['GET'])]
    public function readAction(Notification $notification, NotificationService $notifService, EntityManagerInterface $em): RedirectResponse
    {
        $notification->setIsRead(true);
        $em->persist($notification);
        $em->flush();

        return $this->redirect($notifService->getRedirectUrl($notification));
    }
}
