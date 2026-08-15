<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Notifier\CustomLoginLinkNotification;
use App\Repository\CoasterRepository;
use App\Repository\UserRepository;
use App\Service\EmailValidationService;
use Doctrine\ORM\EntityManagerInterface;
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

class ConnectController extends AbstractController
{
    use TargetPathTrait;

    #[Route(path: '/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler,
        UserRepository $userRepository,
        CoasterRepository $coasterRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        RateLimiterFactory $loginLinkLimiter,
        EmailValidationService $emailValidator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('default_index');
        }

        $bgCoaster = $coasterRepository->findRandomTopRanked(10);

        if ($request->isMethod('POST')) {
            $limiter = $loginLinkLimiter->create($request->getClientIp());
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $this->addFlash('danger', $translator->trans('error.rate_limit', [], 'login'));

                return $this->render('Connect/login.html.twig', [
                    'error' => null,
                    'bgCoaster' => $bgCoaster,
                    'initialStep' => 1,
                    'emailParam' => '',
                ]);
            }

            if (!$this->isCsrfTokenValid('login_action', $request->request->getString('_csrf_token'))) {
                $this->addFlash('danger', $translator->trans('error.rate_limit', [], 'login'));

                return $this->render('Connect/login.html.twig', [
                    'error' => null,
                    'bgCoaster' => $bgCoaster,
                    'initialStep' => 1,
                    'emailParam' => '',
                ]);
            }

            $step = $request->request->getString('_step', 'email');

            if ('email' === $step) {
                return $this->handleEmailStep($request, $notifier, $loginLinkHandler, $userRepository, $translator, $emailValidator);
            }

            if ('register' === $step) {
                return $this->handleRegisterStep($request, $notifier, $loginLinkHandler, $userRepository, $entityManager, $translator, $emailValidator);
            }

            // Unknown step value — redirect to State 1
            return $this->redirectToRoute('login', ['_locale' => $request->getLocale()]);
        }

        // GET: determine which state to show
        $initialStep = 1;
        $emailParam = $request->query->getString('email', '');

        if ('1' === $request->query->getString('sent')) {
            $initialStep = 3;
        } elseif ('register' === $request->query->getString('step')) {
            $initialStep = 2;
        } else {
            $request->getSession()->set('locale_at_login', $request->getLocale());
            $referer = $request->headers->get('referer');
            if ($referer) {
                $parsedPath = parse_url($referer, \PHP_URL_PATH);
                $parsedQuery = parse_url($referer, \PHP_URL_QUERY);
                $this->saveTargetPath(
                    $request->getSession(),
                    'main',
                    $parsedPath.($parsedQuery ? '?'.$parsedQuery : '')
                );
            }
        }

        return $this->render('Connect/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'bgCoaster' => $bgCoaster,
            'initialStep' => $initialStep,
            'emailParam' => $emailParam,
        ]);
    }

    private function handleEmailStep(
        Request $request,
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler,
        UserRepository $userRepository,
        TranslatorInterface $translator,
        EmailValidationService $emailValidator,
    ): Response {
        $email = $request->request->getString('email');

        if ($emailValidator->isValidEmail($email)) {
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User && $user->isEnabled()) {
                // Returning user: send magic login link
                $notifier->send(
                    new CustomLoginLinkNotification(
                        $loginLinkHandler->createLoginLink($user),
                        $translator->trans('email.login_subject', [], 'login'),
                        ['email']
                    ),
                    new Recipient($user->getEmail())
                );

                return $this->redirectToRoute('login', [
                    '_locale' => $request->getLocale(),
                    'sent' => '1',
                    'email' => $email,
                ]);
            }

            if (null === $user) {
                // New user: collect name first
                return $this->redirectToRoute('login', [
                    '_locale' => $request->getLocale(),
                    'step' => 'register',
                    'email' => $email,
                ]);
            }
        }

        // Invalid email, banned, or deleted account: same response for enumeration protection
        return $this->redirectToRoute('login', [
            '_locale' => $request->getLocale(),
            'sent' => '1',
            'email' => $email,
        ]);
    }

    private function handleRegisterStep(
        Request $request,
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        EmailValidationService $emailValidator,
    ): Response {
        $email = $request->request->getString('email');
        $firstName = trim($request->request->getString('firstName'));
        $lastName = trim($request->request->getString('lastName'));

        if ($emailValidator->isValidEmail($email) && '' !== $firstName) {
            $existingUser = $userRepository->findOneBy(['email' => $email]);

            if (null === $existingUser) {
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($firstName);
                if ('' !== $lastName) {
                    $user->setLastName($lastName);
                }
                $user->setPreferredLocale($request->getLocale());
                $ipAddress = $request->getClientIp();
                if (null !== $ipAddress) {
                    $user->setIpAddress($ipAddress);
                }
                $user->setEnabled(true);
                $user->updateDisplayName();

                $entityManager->persist($user);
                $entityManager->flush();

                $notifier->send(
                    new CustomLoginLinkNotification(
                        $loginLinkHandler->createLoginLink($user),
                        $translator->trans('activation.subject', [], 'login'),
                        ['email']
                    ),
                    new Recipient($user->getEmail())
                );
            }
        }

        return $this->redirectToRoute('login', [
            '_locale' => $request->getLocale(),
            'sent' => '1',
            'email' => $email,
        ]);
    }

    /** Route handled in routes.yaml (no locale) */
    public function logout(): void
    {
    }

    /** Initiate Google's oauth2 authentication. Route handled in routes.yaml (no locale). */
    public function connectGoogleStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect([], []);
    }

    /** After going to Google, you're redirected back here. Route handled in routes.yaml (no locale). */
    public function connectGoogleCheck(Request $request): void
    {
    }
}
