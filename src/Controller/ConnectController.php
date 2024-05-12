<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Notifier\CustomLoginLinkNotification;
use App\Repository\UserRepository;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller in charge of authentication routes.
 */
class ConnectController extends AbstractController
{
    use TargetPathTrait;

    /** Display login page */
    #[Route(path: '/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler,
        UserRepository $userRepository,
        TranslatorInterface $translator,
        RateLimiterFactory $loginLinkLimiter,
    ): Response {
        if ($request->isMethod('POST') && $email = $request->request->get('email')) {
            // create a limiter based on a unique identifier of the client
            // (e.g. the client's IP address, a username/email, an API key, etc.)
            $limiter = $loginLinkLimiter->create($request->getClientIp());

            // the argument of consume() is the number of tokens to consume
            // and returns an object of type Limit
            if (false === $limiter->consume(1)->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }

            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User && $user->isEnabled()) {
                $notifier->send(
                    new CustomLoginLinkNotification(
                        $loginLinkHandler->createLoginLink($user),
                        $translator->trans('login.email.title'),
                        ['email']
                    ),
                    new Recipient($user->getEmail())
                );
            }

            // always return success for account enumeration prevention
            $this->addFlash('success', $translator->trans('login.link_sent', ['email' => $email]));
        } else {
            // save referer to redirect after login
            $referer = $request->headers->get('referer');
            if ($referer) {
                $this->saveTargetPath(
                    $request->getSession(),
                    'main',
                    parse_url($referer, \PHP_URL_PATH).'?'.parse_url($referer, \PHP_URL_QUERY)
                );
            }

            // save current locale in session
            $request->getSession()->set('locale_at_login', $request->getLocale());
        }

        return $this->render('connect/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
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
}
