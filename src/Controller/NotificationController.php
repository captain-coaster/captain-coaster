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

    /**
     * Redirects to a notification's target. Deliberately does not mark it
     * read: this link is also the one used in emails, and mail-security
     * gateways commonly prefetch every link in an incoming email to scan it
     * before delivery — a GET here that mutated state would let a scanner
     * silently mark notifications read before the recipient ever saw them,
     * corrupting the readAt signal this whole thing exists to produce. Real
     * in-app clicks mark read via markReadAjax() instead, fired
     * client-side, which a non-JS prefetcher never triggers.
     */
    #[Route(path: '/{id}/read', name: 'notification_read', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function readAction(NotificationRecipient $recipient, NotificationService $notifService): RedirectResponse
    {
        if ($recipient->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->redirect($notifService->getRedirectUrl($recipient));
    }

    /** Marks a single notification read, fired via a client-side beacon on real clicks (see readAction()). */
    #[Route(path: '/{id}/mark-read', name: 'notification_mark_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markReadAjax(NotificationRecipient $recipient, Request $request, NotificationService $notifService): Response
    {
        if ($recipient->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notification_mark_read', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $notifService->markRead($recipient);

        return new Response(null, Response::HTTP_NO_CONTENT);
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
