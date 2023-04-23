<?php

namespace App\Doctrine;

use App\Entity\Image;
use App\Service\ImageManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ImageListener
 * @package App\Doctrine
 */
class ImageListener
{
    private ImageManager $imageManager;

    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * Before persist:
     *  - upload file
     * @throws FilesystemException
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $image = $args->getEntity();
        if (!$image instanceof Image) {
            return;
        }

        $file = $image->getFile();

        // only upload new files
        if ($file instanceof UploadedFile) {
            $fileName = $this->imageManager->upload($file, $image->getCoaster()->getSlug());
            $image->setFilename($fileName);
        }
    }

    /**
     * Before remove :
     *  - remove image file on disk
     * @throws FilesystemException
     */
    public function preRemove(LifecycleEventArgs $args)
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
    public function postRemove(LifecycleEventArgs $args)
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
     *  - update main images
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        if (!$args->getEntity() instanceof Image) {
            return;
        }

        $this->imageManager->setMainImages();
    }
}
