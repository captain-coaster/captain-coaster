<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'clearCoasterCache', entity: RiddenCoaster::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'clearCoasterCache', entity: RiddenCoaster::class)]
#[AsEntityListener(event: Events::postRemove, method: 'clearCoasterCache', entity: RiddenCoaster::class)]
class RiddenCoasterListener
{
    public function __construct(private readonly RiddenCoasterRepository $riddenCoasterRepository)
    {
    }

    /** The coaster page's rating distribution must show a rider's own rating right away. */
    public function clearCoasterCache(RiddenCoaster $riddenCoaster): void
    {
        $this->riddenCoasterRepository->clearRatingStatsCache($riddenCoaster->getCoaster());
    }
}
