<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\LikedImage;
use App\Service\ImageManager;
use Doctrine\ORM\Event\LifecycleEventArgs;

class LikedImageListener
{
    public function __construct(private readonly ImageManager $imageManager)
    {
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        if (!$args->getEntity() instanceof LikedImage) {
            return;
        }

        $this->imageManager->updateLikeCounters();
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        if (!$args->getEntity() instanceof LikedImage) {
            return;
        }

        $this->imageManager->updateLikeCounters();
    }
}
