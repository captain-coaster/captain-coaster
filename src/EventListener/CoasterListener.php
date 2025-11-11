<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Coaster;
use App\Service\SearchCacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'invalidateSearchCache', entity: Coaster::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateSearchCache', entity: Coaster::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateSearchCache', entity: Coaster::class)]
class CoasterListener
{
    public function __construct(
        private readonly SearchCacheService $searchCacheService
    ) {
    }

    public function invalidateSearchCache(): void
    {
        $this->searchCacheService->invalidateSearchCache();
    }
}
