<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Image;
use App\Service\ImageManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Class ImageListener.
 */
class ImageListener
{
    public function __construct(private readonly ImageManager $imageManager, private readonly ChatterInterface $chatter)
    {
    }

    /**
     * Before persist:
     *  - upload file
     *
     * @throws FilesystemException
     * @throws TransportExceptionInterface
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $image = $args->getObject();
        if (!$image instanceof Image) {
            return;
        }

        // only upload new files
        if ($image->getFile() instanceof UploadedFile) {
            $fileName = $this->imageManager->upload($image);
            $image->setFilename($fileName);
        }

        $this->chatter->send(
            (new ChatMessage('A new picture of '.$image->getCoaster()->getName().' is waiting for review'))->transport('discord_notif')
        );
    }

    /**
     * Before remove :
     *  - remove image file on disk
     *
     * @throws FilesystemException
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $image = $args->getEntity();
        if (!$image instanceof Image) {
            return;
        }

        $this->imageManager->remove($image->getFilename());
    }

    /**
     * After remove :
     *  - update main images
     *  - remove cache
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $image = $args->getEntity();
        if (!$image instanceof Image) {
            return;
        }

        $this->imageManager->setMainImages();
        $this->imageManager->removeCache($image);
    }

    /**
     * After update (enabled set to 1 is an update)
     *  - update main images.
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        if (!$args->getEntity() instanceof Image) {
            return;
        }

        $this->imageManager->setMainImages();
    }
}
