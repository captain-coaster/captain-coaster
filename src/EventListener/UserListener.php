<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\SearchCacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'invalidateSearchCache', entity: User::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateSearchCache', entity: User::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateSearchCache', entity: User::class)]
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: User::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: User::class)]
class UserListener
{
    public function __construct(
        private readonly SearchCacheService $searchCacheService,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function invalidateSearchCache(): void
    {
        $this->searchCacheService->invalidateSearchCache();
    }

    public function prePersist(User $user): void
    {
        $user->updateDisplayName();
    }

    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        $nameChanged = $args->hasChangedField('firstName') || $args->hasChangedField('lastName');
        $formatChanged = $args->hasChangedField('displayNameFormat');

        // Update display name if any relevant field changed
        if ($nameChanged || $formatChanged) {
            $user->updateDisplayName();
        }

        // Update nameChangedAt only if name fields changed and user can change name
        if ($nameChanged && $user->canChangeName()) {
            $user->setNameChangedAt(new \DateTime());
        }
    }

    public function postUpdate(User $user): void
    {
        if (!$user->isEnabled()) {
            $user->setEmailNotification(false);
            $this->em->persist($user);
            $this->em->flush();
        }
    }
}
