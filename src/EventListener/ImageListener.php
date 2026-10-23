<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Image;
use App\Service\HeroService;
use App\Service\ImageManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Image::class)]
#[AsDoctrineListener(event: Events::postFlush)]
class ImageListener
{
    private bool $mainImagesStale = false;

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly HeroService $heroService,
    ) {
    }

    /** Before remove: remove image file and its v2 variants on storage (S3), while the id is still set */
    public function preRemove(Image $image, PreRemoveEventArgs $args): void
    {
        $this->imageManager->remove($image->getFilename());
        $this->imageManager->removeVariants($image);
    }

    public function postRemove(Image $image, PostRemoveEventArgs $args): void
    {
        $this->mainImagesStale = true;
    }

    /** An image that is disabled and stays so (an upload's own flush) can't be a main image. */
    public function postUpdate(Image $image, PostUpdateEventArgs $event): void
    {
        $changeSet = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($image);
        if (!$image->isEnabled() && !isset($changeSet['enabled'])) {
            return;
        }

        $this->mainImagesStale = true;
    }

    /**
     * Main images are recomputed after the commit, not inside the flush's transaction: the
     * statement rewrites every coaster, and a deadlock there rolled the whole transaction back,
     * silently losing the image change that triggered it (a moderation verdict, for one).
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->mainImagesStale) {
            return;
        }

        $this->mainImagesStale = false;
        $this->imageManager->setMainImages();
        $this->heroService->invalidate();
    }
}
