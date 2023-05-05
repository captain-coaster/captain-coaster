<?php

namespace App\Doctrine;

use App\Entity\LikedImage;
use App\Service\ImageManager;
use Doctrine\ORM\Event\LifecycleEventArgs;

class LikedImageListener
{
    /**
     * ImageUploadListener constructor.
     */
    public function __construct(private readonly ImageManager $imageManager)
    {
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        if (!$args->getEntity() instanceof LikedImage) {
            return;
        }

        $this->imageManager->updateLikeCounters();
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        if (!$args->getEntity() instanceof LikedImage) {
            return;
        }

        $this->imageManager->updateLikeCounters();
    }
}
