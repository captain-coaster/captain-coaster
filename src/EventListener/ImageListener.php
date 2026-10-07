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

    /** After remove: update main images */
    public function postRemove(Image $image, PostRemoveEventArgs $args): void
    {
        $this->imageManager->setMainImages();
        $this->heroService->invalidate();
    }

    /** After update (enabled set to 1 is an update): update main images */
    public function postUpdate(Image $image, PostUpdateEventArgs $event): void
    {
        $this->imageManager->setMainImages();
        $this->heroService->invalidate();
    }
}
