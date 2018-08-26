<?php

namespace BddBundle\Doctrine;

use BddBundle\Entity\Image;
use BddBundle\Service\ImageManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ImageListener
 * @package BddBundle\Doctrine
 */
class ImageListener
{
    /**
     * @var ImageManager
     */
    private $imageManager;

    /**
     * ImageUploadListener constructor.
     * @param ImageManager $imageManager
     */
    public function __construct(ImageManager $imageManager)
    {
        $this->imageManager = $imageManager;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->uploadFile($entity);
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        $this->removeFile($entity);
    }

    /**
     * Update main image of a coaster after remove.
     */
    public function postRemove()
    {
        $this->imageManager->setMainImages();
    }

    /**
     * Update main image of a coaster after update.
     */
    public function postUpdate()
    {
        $this->imageManager->setMainImages();
    }

    /**
     * @param $entity
     * @throws \Exception
     */
    private function uploadFile($entity)
    {
        // upload only works for Image entities
        if (!$entity instanceof Image) {
            return;
        }

        $file = $entity->getFile();

        // only upload new files
        if ($file instanceof UploadedFile) {
            $fileName = $this->imageManager->upload($file);
            $entity->setFilename($fileName);
        }
    }

    /**
     * @param $entity
     */
    private function removeFile($entity)
    {
        // upload only works for Image entities
        if (!$entity instanceof Image) {
            return;
        }

        $this->imageManager->remove($entity->getFilename());
    }
}
