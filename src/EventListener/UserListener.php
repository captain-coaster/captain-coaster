<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\ProfilePictureManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: User::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: User::class)]
class UserListener
{
    public function __construct(
        private readonly ProfilePictureManager $profilePictureManager
    ) {
    }

    public function prePersist(User $user): void
    {
        $user->updateDisplayName();
    }

    /** Before remove: delete profile picture from storage (S3). Images handled by ImageListener via cascade. */
    public function preRemove(User $user, PreRemoveEventArgs $args): void
    {
        $profilePicture = $user->getProfilePicture();
        if (null !== $profilePicture) {
            $this->profilePictureManager->deleteProfilePicture($profilePicture);
        }
    }

    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        $nameChanged = $args->hasChangedField('firstName') || $args->hasChangedField('lastName');
        $formatChanged = $args->hasChangedField('displayNameFormat');

        // Update display name if any relevant field changed
        if ($nameChanged || $formatChanged) {
            $user->updateDisplayName();
        }

        // Track when name fields actually changed (not format preference)
        if ($nameChanged) {
            $user->setNameChangedAt(new \DateTime());
        }

        // When user account is disabled
        if ($args->hasChangedField('enabled') && !$user->isEnabled()) {
            $user->setEmailNotification(false);

            // If deletedAt is set, this is a user-initiated deletion
            // If not, this is an admin ban
            if (null === $user->getDeletedAt() && null === $user->getBannedAt()) {
                $user->setBannedAt(new \DateTime());
            }
        }

        // When user account is re-enabled, clear ban and deletion timestamps
        if ($args->hasChangedField('enabled') && $user->isEnabled()) {
            $user->setBannedAt(null);
            $user->setDeletedAt(null);
        }
    }
}
