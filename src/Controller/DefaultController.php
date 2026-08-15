<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\ContactType;
use App\Repository\CoasterRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\HeroService;
use App\Service\StatService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
        // 302 (not 301): the target locale is negotiated per user (preferred locale /
        // Accept-Language), so a permanent redirect would let a shared cache pin everyone
        // to one locale and would stick after a user changes their preference.
        if ($this->getUser()) {
            return $this->redirectToRoute('default_index', ['_locale' => $this->getUser()->getPreferredLocale()], 302);
        }

        /** @var array<string> $locales */
        $locales = $this->getParameter('app_locales_array');

        // Honour the locale an anonymous visitor last browsed in (see LocaleSubscriber),
        // falling back to browser Accept-Language negotiation.
        $sessionLocale = $request->hasSession() ? $request->getSession()->get('_locale') : null;
        $locale = \in_array($sessionLocale, $locales, true) ? $sessionLocale : $request->getPreferredLanguage($locales);

        return $this->redirectToRoute('default_index', [
            '_locale' => $locale,
        ], 302);
    }

    /**
     * Index of application.
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws \Exception
     */
    #[Route(path: '/', name: 'default_index', methods: ['GET'])]
    public function index(
        Request $request,
        StatService $statService,
        RiddenCoasterRepository $riddenCoasterRepository,
        CoasterRepository $coasterRepository,
        HeroService $heroService,
    ): Response {
        $displayReviewsInAllLanguages = false;
        if ($user = $this->getUser()) {
            $displayReviewsInAllLanguages = $user->isDisplayReviewsInAllLanguages();
        }

        $topRanked = $coasterRepository->getTopRanked(5);

        return $this->render('Default/index.html.twig', [
            'ratingFeed' => $riddenCoasterRepository->getLatestRatings(6),
            'stats' => $statService->getIndexStats(),
            'reviews' => $riddenCoasterRepository->getLatestLikedReviews($request->getLocale(), 3, $displayReviewsInAllLanguages),
            'topRanked' => $topRanked,
            'hero' => $heroService->pick($topRanked),
            'displayReviewsInAllLanguages' => $displayReviewsInAllLanguages,
        ]);
    }

    /**
     * AJAX endpoint for the nearby coasters widget.
     * Returns a server-rendered Twig partial (HTML) — no JS templating needed.
     */
    #[Route(path: '/api/nearby-coasters', name: 'api_nearby_coasters', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function nearbyCoasters(Request $request, CoasterRepository $coasterRepository): Response
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new BadRequestHttpException('Invalid coordinates');
        }

        $latitude = (float) $lat;
        $longitude = (float) $lng;

        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            throw new BadRequestHttpException('Coordinates out of range');
        }

        $maxDistance = max(50, min(500, $request->query->getInt('radius', 200)));
        $limit = max(1, min(10, $request->query->getInt('limit', 5)));

        $coasters = $coasterRepository->findNearbyCoasters($latitude, $longitude, $maxDistance, $limit);

        if (empty($coasters)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('partials/_nearby_coasters.html.twig', [
            'coasters' => $coasters,
            'kmLabel' => $request->query->get('kmLabel', 'km'),
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
                ->html($this->renderView('email/contact.txt.twig', [
                    'name' => $formData['name'],
                    'message' => $formData['message'],
                    'isLoggedIn' => (bool) $user,
                    'email' => $formData['email'] ?? null,
                ]));

            if (!empty($formData['email'])) {
                $message->replyTo($formData['email']);
            }

            $mailer->send($message);

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
