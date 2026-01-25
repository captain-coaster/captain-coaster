<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: User::class)]
class UserListener
{
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

        // Track when name fields actually changed (not format preference)
        if ($nameChanged) {
            $user->setNameChangedAt(new \DateTime());
        }

        // When user account is disabled (banned), set bannedAt and disable notifications
        if ($args->hasChangedField('enabled') && !$user->isEnabled()) {
            $user->setEmailNotification(false);

            // Set bannedAt timestamp if not already set
            if (null === $user->getBannedAt()) {
                $user->setBannedAt(new \DateTime());
            }
        }
    }
}
