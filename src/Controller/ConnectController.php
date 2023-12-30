<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
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
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Controller in charge of authentication routes.
 */
class ConnectController extends AbstractController
{
    use TargetPathTrait;

    /** Display login page */
    #[Route(path: '/login', name: 'login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        // save referer to redirect after login
        $referer = $request->headers->get('referer');
        if ($referer) {
            $this->saveTargetPath(
                $request->getSession(),
                'main',
                parse_url($referer, \PHP_URL_PATH).'?'.parse_url($referer, \PHP_URL_QUERY)
            );
        }

        return $this->render('connect/login.html.twig');
    }

    public function loginCheck(): void
    {
    }

    /** Route handled in routes.yaml (no locale) */
    public function logout(): void
    {
    }

    /** Initiate Google's oauth2 authentication. Route handled in routes.yaml (no locale). */
    public function connectGoogleStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        // will redirect to Google!
        return $clientRegistry->getClient('google')->redirect([], []);
    }

    /** After going to Google, you're redirected back here. Route handled in routes.yaml (no locale). */
    public function connectGoogleCheck(Request $request): void
    {
        // left blank as it is handled inside GoogleAuthenticator
    }

    /** Initiate Facebook's oauth2 authentication. Route handled in routes.yaml (no locale). */
    public function connectFacebookStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        // will redirect to Facebook!
        return $clientRegistry->getClient('facebook')->redirect([], []);
    }

    /** After going to Facebook, you're redirected back here. Route handled in routes.yaml (no locale). */
    public function connectFacebookCheck(Request $request): void
    {
        // left blank as it is handled inside FacebookAuthenticator
    }

    #[Route('/login/link/start', name: 'login_link_start')]
    public function requestLoginLink(NotifierInterface $notifier, LoginLinkHandlerInterface $loginLinkHandler, UserRepository $userRepository, Request $request): Response
    {
        // deny access for now
        $this->denyAccessUnlessGranted('ROLE_PREVIEW_FEATURE');

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
}
