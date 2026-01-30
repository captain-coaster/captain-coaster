<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\ContactType;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\StatService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for index pages.
 */
class DefaultController extends BaseController
{
    /** Root of application without locale, redirect to browser language if defined. */
    public function root(Request $request): RedirectResponse
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('default_index', ['_locale' => $this->getUser()->getPreferredLocale()], 301);
        }

        /** @var array<string> $locales */
        $locales = $this->getParameter('app_locales_array');

        return $this->redirectToRoute('default_index', [
            '_locale' => $request->getPreferredLanguage($locales),
        ], 301);
    }

    /**
     * Index of application.
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws \Exception
     */
    #[Route(path: '/', name: 'default_index', methods: ['GET'])]
    public function index(Request $request, StatService $statService, RiddenCoasterRepository $riddenCoasterRepository, ImageRepository $imageRepository): Response
    {
        $displayReviewsInAllLanguages = false;
        $missingImages = [];
        if ($user = $this->getUser()) {
            $displayReviewsInAllLanguages = $this->getUser()->isDisplayReviewsInAllLanguages();
            $missingImages = $riddenCoasterRepository->findCoastersWithNoImage($user);
        }

        return $this->render('Default/index.html.twig', [
            'ratingFeed' => $riddenCoasterRepository->getLatestRatings(6),
            'image' => $imageRepository->findLatestLikedImage(),
            'stats' => $statService->getIndexStats(),
            'reviews' => $riddenCoasterRepository->getLatestReviews($request->getLocale(), 3, $displayReviewsInAllLanguages),
            'missingImages' => $missingImages,
            'displayReviewsInAllLanguages' => $displayReviewsInAllLanguages,
        ]);
    }

    /**
     * Contact form.
     *
     * @throws TransportExceptionInterface
     */
    #[Route(path: '/contact', name: 'default_contact', methods: ['GET', 'POST'])]
    public function contactAction(Request $request, MailerInterface $mailer, ChatterInterface $chatter, TranslatorInterface $translator): RedirectResponse|Response
    {
        $initialData = [];
        $user = $this->getUser();

        // Pre-populate form with user data if logged in
        if ($user) {
            $initialData = [
                'name' => $user->getDisplayName(),
                'email' => $user->getEmail(),
            ];
        }

        /** @var Form $form */
        $form = $this->createForm(ContactType::class, $initialData, ['is_logged_in' => (bool) $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, message: string, email?: string|null} $formData */
            $formData = $form->getData();

            /** @var string $contactMailTo */
            $contactMailTo = $this->getParameter('app_contact_mail_to');

            $message = (new Email())
                ->to($contactMailTo)
                ->subject($translator->trans('contact.email.title'))
                ->html($this->renderView('Default/contact_mail.txt.twig', [
                    'name' => $formData['name'],
                    'message' => $formData['message'],
                    'isLoggedIn' => (bool) $user,
                    'email' => $formData['email'] ?? null,
                ]));

            if (!empty($formData['email'])) {
                $message->replyTo($formData['email']);
            }

            $mailer->send($message);

            // send notification
            $chatter->send((new ChatMessage('We just received new message from '.$formData['name']."\n\n".$formData['message']))->transport('discord_notif'));

            $this->addFlash('success', $translator->trans('contact.flash.success', ['%name%' => $formData['name']]));

            return $this->redirectToRoute('default_contact');
        }

        return $this->render('Default/contact.html.twig', [
            'form' => $form,
            'isLoggedIn' => (bool) $user,
        ]);
    }

    #[Route(path: '/terms-conditions', name: 'app_terms_conditions', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('Default/terms_conditions.html.twig');
    }
}
