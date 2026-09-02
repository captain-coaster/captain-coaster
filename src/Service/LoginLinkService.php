<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Notifier\CustomLoginLinkNotification;
use App\Repository\UserRepository;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginLinkService
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $loginLinkLimiter,
        private readonly RateLimiterFactoryInterface $loginLinkEmailLimiter,
        private readonly EmailValidationService $emailValidator,
        private readonly UserRepository $userRepository,
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly NotifierInterface $notifier,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Sends a login link when the email is valid and matches an enabled user;
     * silently no-ops otherwise (account enumeration prevention is the caller's job).
     *
     * Rate limited by IP and by email (normalized) so an attacker rotating IPs
     * can't spam a single victim's inbox.
     *
     * @return bool true if the request was rate limited and no email was sent
     */
    public function requestLoginLink(string $email, ?string $clientIp): bool
    {
        $ipAccepted = $this->loginLinkLimiter->create($clientIp)->consume(1)->isAccepted();
        $emailAccepted = $this->loginLinkEmailLimiter->create(mb_strtolower($email))->consume(1)->isAccepted();

        if (!$ipAccepted || !$emailAccepted) {
            return true;
        }

        if ($this->emailValidator->isValidEmail($email)) {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User && $user->isEnabled()) {
                $this->notifier->send(
                    new CustomLoginLinkNotification(
                        $this->loginLinkHandler->createLoginLink($user),
                        $this->translator->trans('login.email.title'),
                        ['email']
                    ),
                    new Recipient($user->getEmail())
                );
            }
        }

        return false;
    }
}
