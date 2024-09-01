<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\LikedImage;
use App\Service\ImageManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'updateLikeCounters', entity: LikedImage::class)]
#[AsEntityListener(event: Events::postRemove, method: 'updateLikeCounters', entity: LikedImage::class)]
class LikedImageListener
{
    public function __construct(private readonly ImageManager $imageManager)
    {
    }

    public function updateLikeCounters(): void
    {
        $this->imageManager->updateLikeCounters();
    }
}
