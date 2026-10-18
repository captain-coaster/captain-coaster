<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Image;
use App\Service\HeroService;
use App\Service\ImageManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Image::class)]
class ImageListener
{
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

    /** After remove: update main images, remove cache */
    public function postRemove(Image $image, PostRemoveEventArgs $args): void
    {
        $this->imageManager->setMainImages();
        $this->imageManager->removeCache($image);
        $this->heroService->invalidate();
    }

    /**
     * After update: update main images, unless the image is disabled and stays so (an upload's
     * own flush): it can't be a main image, and the statement, which rewrites every coaster,
     * deadlocked inside the upload's transaction.
     */
    public function postUpdate(Image $image, PostUpdateEventArgs $event): void
    {
        $changeSet = $event->getObjectManager()->getUnitOfWork()->getEntityChangeSet($image);
        if (!$image->isEnabled() && !isset($changeSet['enabled'])) {
            return;
        }

        $this->imageManager->setMainImages();
        $this->heroService->invalidate();
    }
}
