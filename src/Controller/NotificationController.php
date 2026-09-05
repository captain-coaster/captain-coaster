<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NotificationRecipient;
use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/notifications')]
class NotificationController extends AbstractController
{
    private const int PAGE_SIZE = 20;

    #[Route(path: '', name: 'notification_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, NotificationRecipientRepository $notificationRecipientRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $beforeCreatedAt = null;
        if (null !== $before = $request->query->get('before')) {
            $beforeCreatedAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $before) ?: null;
        }
        $beforeId = $request->query->getInt('beforeId') ?: null;

        $recipients = $notificationRecipientRepository->findPageForUser($user, self::PAGE_SIZE + 1, $beforeCreatedAt, $beforeId);

        $hasMore = \count($recipients) > self::PAGE_SIZE;
        $recipients = \array_slice($recipients, 0, self::PAGE_SIZE);
        $last = end($recipients) ?: null;

        if ($request->isXmlHttpRequest()) {
            $response = $this->render('Notification/_notification_list.html.twig', ['recipients' => $recipients]);
            $response->headers->set('X-Notification-Has-More', $hasMore ? '1' : '0');
            $response->headers->set('X-Notification-Next-Before', (string) $last?->getCreatedAt()?->format(\DateTimeInterface::ATOM));
            $response->headers->set('X-Notification-Next-Before-Id', (string) $last?->getId());

            return $response;
        }

        return $this->render('Notification/index.html.twig', [
            'recipients' => $recipients,
            'hasMore' => $hasMore,
            'nextBefore' => $last?->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'nextBeforeId' => $last?->getId(),
            'unreadCount' => $notificationRecipientRepository->countUnreadForUser($user),
        ]);
    }

    /** Read a notification. */
    #[Route(path: '/{id}/read', name: 'notification_read', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function readAction(NotificationRecipient $recipient, NotificationService $notifService): RedirectResponse
    {
        if ($recipient->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $notifService->markRead($recipient);

        return $this->redirect($notifService->getRedirectUrl($recipient));
    }

    #[Route(path: '/mark-all-read', name: 'notification_mark_all_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markAllRead(Request $request, NotificationRecipientRepository $notificationRecipientRepository): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('notification_mark_all_read', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $notificationRecipientRepository->markAllReadForUser($user);

        return $this->redirectToRoute('notification_index');
    }
}
