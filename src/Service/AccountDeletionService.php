<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
    }

    public function scheduleAccountDeletion(User $user): void
    {
        $user->setDeletedAt(new \DateTime());
        $user->setEnabled(false);

        $this->em->persist($user);
        $this->em->flush();

        $this->logger->info('Account scheduled for deletion', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'deleted_at' => $user->getDeletedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function permanentlyDeleteAccount(User $user): void
    {
        $userId = $user->getId();
        $userEmail = $user->getEmail();

        // UserListener::preRemove calls UserFileDeletionService::deleteUserFiles()
        // Database cascade handles related entities
        $this->em->remove($user);
        $this->em->flush();

        $this->logger->info('Account permanently deleted', [
            'user_id' => $userId,
            'email' => $userEmail,
        ]);
    }
}
