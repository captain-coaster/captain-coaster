<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\UserRepository;
use App\Service\StatService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;

/**
 * Controller in charge of authentication routes
 */
class ConnectController extends AbstractController
{
    /**
     * Display login page
     */
    #[Route(path: '/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('connect/login.html.twig');
    }

    public function loginCheck(): void
    {
    }

    /**
     * Route handled in routes.yaml (no locale)
     */
    public function logout(): void
    {
    }

    /**
     * Initiate Google's oauth2 authentication
     * Route handled in routes.yaml (no locale)
     */
    public function connectGoogleStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        // will redirect to Google!
        return $clientRegistry->getClient('google')->redirect([], []);
    }

    /**
     * After going to Google, you're redirected back here
     * Route handled in routes.yaml (no locale)
     */
    public function connectGoogleCheck(Request $request): void
    {
        // left blank as it is handled inside GoogleAuthenticator
    }

    #[Route('/login/link/start', name: 'login_link_start')]
    public function requestLoginLink(
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler,
        UserRepository $userRepository,
        Request $request
    ): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $userRepository->findOneBy(['email' => $email]);

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);

            // create a notification based on the login link details
            $notification = new LoginLinkNotification(
                $loginLinkDetails,
                'Welcome to MY WEBSITE!' // email subject
            );
            // create a recipient for this user
            $recipient = new Recipient($user->getEmail());

            // send the notification to the user
            $notifier->send($notification, $recipient);

            // render a "Login link is sent!" page
            return $this->render('connect/login.html.twig');
        }

        return $this->render('connect/login.html.twig');
    }

    #[Route('/protected', name: 'protected')]
    public function protected(
        Request                 $request,
        StatService             $statService,
        RiddenCoasterRepository $riddenCoasterRepository,
        ImageRepository         $imageRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $missingImages = [];
        if (($user = $this->getUser()) instanceof User) {
            $missingImages = $riddenCoasterRepository->findCoastersWithNoImage($user);
        }

        return $this->render(
            'Default/index.html.twig',
            [
                'ratingFeed' => $riddenCoasterRepository->findBy([], ['updatedAt' => 'DESC'], 6),
                'image' => $imageRepository->findLatestImage(),
                'stats' => $statService->getIndexStats(),
                'reviews' => $riddenCoasterRepository->getLatestReviewsByLocale($request->getLocale()),
                'missingImages' => $missingImages,
            ]
        );
    }
}
