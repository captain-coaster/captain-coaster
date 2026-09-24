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

    /** Before persist: set hash; the file itself is written in postPersist(), once the id exists. */
    public function prePersist(Image $image, PrePersistEventArgs $event): void
    {
        // only upload new files
        if ($image->getFile() instanceof UploadedFile) {
            $this->imageManager->setImageHash($image);
            // Placeholder for the NOT NULL column, replaced by `{id}.jpg` in postPersist().
            $image->setFilename('');
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
        if (!$image->getFile() instanceof UploadedFile) {
            return;
        }

        // Still inside the flush's transaction: if the S3 write throws, the row is rolled back
        // instead of pointing at a missing original.
        $filename = $this->imageManager->upload($image);
        $image->setFilename($filename);
        $em = $event->getObjectManager();
        $em->getConnection()->update('image', ['filename' => $filename], ['id' => $image->getId()]);
        // The row now holds the real name: keep the next flush from issuing the same UPDATE.
        $em->getUnitOfWork()->setOriginalEntityProperty(spl_object_id($image), 'filename', $filename);

        $this->messageBus->dispatch(new AnalyzeImageMessage($image->getId()));
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

    /** After update (enabled set to 1 is an update): update main images */
    public function postUpdate(Image $image, PostUpdateEventArgs $event): void
    {
        $this->imageManager->setMainImages();
        $this->heroService->invalidate();
    }
}
