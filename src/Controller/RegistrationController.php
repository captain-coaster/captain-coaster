<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\RegistrationFormType;
use App\Notifier\CustomLoginLinkNotification;
use App\Service\EmailValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        EmailValidationService $emailValidator,
        RateLimiterFactory $registrationLimiter,
        NotifierInterface $notifier,
        LoginLinkHandlerInterface $loginLinkHandler
    ): Response {
        // Redirect if already logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('default_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $registrationLimiter->create($request->getClientIp());
            $limit = $limiter->consume(1);

            if (false === $limit->isAccepted()) {
                $this->addFlash('danger', $translator->trans('register.rate_limit_exceeded'));

                return $this->render('Registration/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            // Only create account if email is valid
            if ($emailValidator->isValidEmail($user->getEmail())) {
                // Create user as enabled (no verification needed)
                $user->setPreferredLocale($request->getLocale());
                $ipAddress = $request->getClientIp();
                if (null !== $ipAddress) {
                    $user->setIpAddress($ipAddress);
                }
                $user->setEnabled(true);
                $user->updateDisplayName();

                $entityManager->persist($user);
                $entityManager->flush();

                // Send login link (same as login page)
                $notifier->send(
                    new CustomLoginLinkNotification(
                        $loginLinkHandler->createLoginLink($user),
                        $translator->trans('login.email.title'),
                        ['email']
                    ),
                    new Recipient($user->getEmail())
                );
            }

            $this->addFlash('success', $translator->trans('register.link_sent', ['email' => $user->getEmail()]));

            // Redirect to login page after successful registration
            return $this->redirectToRoute('login');
        }

        return $this->render('Registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
