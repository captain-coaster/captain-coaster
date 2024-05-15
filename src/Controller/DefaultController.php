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
use Symfony\Component\Routing\Annotation\Route;
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
            return $this->redirectToRoute('bdd_index', ['_locale' => $this->getUser()->getPreferredLocale()], 301);
        }

        return $this->redirectToRoute('bdd_index', [
            '_locale' => $request->getPreferredLanguage($this->getParameter('app_locales_array')),
        ], 301);
    }

    /**
     * Index of application.
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws \Exception
     */
    #[Route(path: '/', name: 'bdd_index', methods: ['GET'])]
    public function index(Request $request, StatService $statService, RiddenCoasterRepository $riddenCoasterRepository, ImageRepository $imageRepository): Response
    {
        $missingImages = [];
        if ($user = $this->getUser()) {
            $missingImages = $riddenCoasterRepository->findCoastersWithNoImage($user);
        }

        return $this->render('Default/index.html.twig', [
            'ratingFeed' => $riddenCoasterRepository->findBy([], ['updatedAt' => 'DESC'], 6),
            'image' => $imageRepository->findLatestImage(),
            'stats' => $statService->getIndexStats(),
            'reviews' => $riddenCoasterRepository->getLatestReviewsByLocale($request->getLocale()),
            'missingImages' => $missingImages,
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
        /** @var Form $form */
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $message = (new Email())
                ->to($this->getParameter('app_contact_mail_to'))
                ->replyTo($data['email'])
                ->subject($translator->trans('contact.email.title'))
                ->html($this->renderView('Default/contact_mail.txt.twig', ['name' => $data['name'], 'message' => $data['message']]));
            $mailer->send($message);

            // send notification
            $chatter->send((new ChatMessage('We just received new message from '.$data['name']."\n\n".$data['message']))->transport('discord_notif'));

            $this->addFlash('success', $translator->trans('contact.flash.success', ['%name%' => $data['name']]));

            return $this->redirectToRoute('default_contact');
        }

        return $this->render('Default/contact.html.twig', ['form' => $form]);
    }

    #[Route(path: '/terms-conditions', name: 'app_terms_conditions', methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('Default/terms_conditions.html.twig');
    }
}
