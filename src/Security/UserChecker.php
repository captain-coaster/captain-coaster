<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEnabled()) {
            // Determine the reason for disabled account
            if ($user->isDeleted()) {
                $this->logger->warning('Login attempt for deleted account', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'deleted_at' => $user->getDeletedAt()?->format('Y-m-d H:i:s'),
                ]);

                throw new CustomUserMessageAccountStatusException('login.account_deleted');
            }

            // Account is banned by admin
            $this->logger->warning('Login attempt for banned account', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'banned_at' => $user->getBannedAt()?->format('Y-m-d H:i:s'),
            ]);

            throw new CustomUserMessageAccountStatusException('login.account_banned');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
    }
}
