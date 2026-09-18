<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Image;
use App\Message\AnalyzeImageMessage;
use App\Service\HeroService;
use App\Service\ImageManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Image::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Image::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Image::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Image::class)]
class ImageListener
{
    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly HeroService $heroService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /** Before persist: set hash and upload file to storage (S3) */
    public function prePersist(Image $image, PrePersistEventArgs $event): void
    {
        // only upload new files
        if ($image->getFile() instanceof UploadedFile) {
            $this->imageManager->setImageHash($image);
            $fileName = $this->imageManager->upload($image);
            $image->setFilename($fileName);
        }
    }

    /**
     * After persist: dispatch the GenAI moderation/focal-point analysis, async so the upload
     * request doesn't wait on a Bedrock round-trip. Discord no longer fires here on every
     * upload -- ImageReportListener now posts only for images the analysis actually flags,
     * turning the channel back into a worklist instead of a 24h-hoping-to-catch-something feed.
     */
    public function postPersist(Image $image, PostPersistEventArgs $event): void
    {
        // Same guard as prePersist() -- only genuinely new uploads, not every persist event.
        if ($image->getFile() instanceof UploadedFile) {
            $this->messageBus->dispatch(new AnalyzeImageMessage($image->getId()));
        }
    }

    /** Before remove: remove image file on storage (S3) */
    public function preRemove(Image $image, PreRemoveEventArgs $args): void
    {
        $this->imageManager->remove($image->getFilename());
    }

    /** After remove: update main images, remove cache */
    public function postRemove(Image $image, PostRemoveEventArgs $args): void
    {
        $this->imageManager->setMainImages();
        $this->imageManager->removeCache($image);
        $this->heroService->invalidate();
    }

    /** After update (enabled set to 1 is an update): update main images */
    public function postUpdate(Image $image, PostUpdateEventArgs $event): void
    {
        $this->imageManager->setMainImages();
        $this->heroService->invalidate();
    }
}
