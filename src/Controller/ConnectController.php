<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\LoginFormType;
use App\Service\LoginLinkService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
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
        TranslatorInterface $translator,
        LoginLinkService $loginLinkService,
    ): Response {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('default_index');
        }

        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);
        $rateLimitExceeded = false;
        $displayForm = $form;

        if ($form->isSubmitted() && $form->isValid()) {
            $emailString = (string) $form->get('email')->getData();

            $rateLimitExceeded = $loginLinkService->requestLoginLink($emailString, $request->getClientIp());

            if ($rateLimitExceeded) {
                $this->addFlash('danger', $translator->trans('login.rate_limit_exceeded'));
            } else {
                // always return success for account enumeration prevention
                $this->addFlash('success', $translator->trans('login.link_sent', ['email' => $emailString]));
            }

            // fresh form: the submitted one carries a spent Turnstile token
            $displayForm = $this->createForm(LoginFormType::class);
        } elseif (!$form->isSubmitted()) {
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
            'rateLimitExceeded' => $rateLimitExceeded,
            'loginForm' => $displayForm,
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
