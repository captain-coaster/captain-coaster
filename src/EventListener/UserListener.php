<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\LikedImageRepository;
use App\Service\HeroService;
use App\Service\ImageManager;
use App\Service\ProfilePictureManager;
use App\Service\SearchCacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: User::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: User::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: User::class)]
#[AsDoctrineListener(event: Events::preFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
class UserListener
{
    /** Set by preUpdate, consumed by postUpdate: whether a field shown in search results changed. */
    private bool $searchStale = false;

    /** Set by preUpdate, consumed by postFlush: the member was banned, deleted or restored. */
    private bool $enabledChanged = false;

    public function __construct(
        private readonly ProfilePictureManager $profilePictureManager,
        private readonly SearchCacheService $searchCacheService,
        private readonly ImageManager $imageManager,
        private readonly LikedImageRepository $likedImageRepository,
        private readonly HeroService $heroService,
        private readonly LoggerInterface $logger,
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

        // Only these fields: a User is updated on every sign-in, and clearing the whole search pool each time would
        // make it useless. firstName/lastName/displayNameFormat stand for displayName, recomputed above, outside
        // the change set.
        foreach (['firstName', 'lastName', 'displayNameFormat', 'displayName', 'slug', 'enabled'] as $field) {
            $this->searchStale = $this->searchStale || $args->hasChangedField($field);
        }

        // Track when name fields actually changed (not format preference)
        if ($nameChanged) {
            $user->setNameChangedAt(new \DateTime());
        }

        $this->enabledChanged = $this->enabledChanged || $args->hasChangedField('enabled');

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

    /** A renamed, banned or deleted member must leave the search results right away. Cleared once written. */
    public function postUpdate(): void
    {
        if ($this->searchStale) {
            $this->searchStale = false;
            $this->searchCacheService->invalidateSearchCache();
        }
    }

    /** A flush that failed after marking it must not make the next one recompute for nothing. */
    public function preFlush(PreFlushEventArgs $args): void
    {
        $this->enabledChanged = false;
    }

    /**
     * A banned, deleted or restored member's likes and photos leave or rejoin the counters, the coasters' main images
     * and the hero: a main image hidden by EnabledUserFilter would fail to load. After the commit, like ImageListener,
     * so that a deadlock in these global updates can't roll the ban back.
     */
    public function postFlush(): void
    {
        if (!$this->enabledChanged) {
            return;
        }

        $this->enabledChanged = false;
        $this->heroService->invalidate();
        // The change is committed: a failed recount must not answer it with an error. The nightly
        // app:stats:update recounts likes, the next photo change recomputes main images.
        try {
            $this->likedImageRepository->updateAllLikeCounts();
        } catch (\Throwable $e) {
            $this->logger->error('Like counters not recounted after a member was enabled or disabled', ['exception' => $e]);
        }
        $this->imageManager->setMainImages();
    }
}
