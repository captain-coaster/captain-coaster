<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Notifier\CustomLoginLinkNotification;
use App\Repository\UserRepository;
use App\Service\EmailValidationService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
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
        EmailValidationService $emailValidator,
    ): Response {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('default_index');
        }

        if ($request->isMethod('POST') && $email = $request->request->get('email')) {
            $limiter = $loginLinkLimiter->create($request->getClientIp());
            $limit = $limiter->consume(1);

            if (false === $limit->isAccepted()) {
                $this->addFlash('danger', $translator->trans('login.rate_limit_exceeded'));

                return $this->render('Connect/login.html.twig', [
                    'error' => $authenticationUtils->getLastAuthenticationError(),
                    'rateLimitExceeded' => true,
                ]);
            }

            $emailString = (string) $email;

            // Only send login link if email is valid
            if ($emailValidator->isValidEmail($emailString)) {
                $user = $userRepository->findOneBy(['email' => $emailString]);

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
            }

            // always return success for account enumeration prevention
            $this->addFlash('success', $translator->trans('login.link_sent', ['email' => $emailString]));
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

        return $this->render('Connect/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'rateLimitExceeded' => false,
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
