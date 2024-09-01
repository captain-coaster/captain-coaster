<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Controller\SearchController;
use App\Entity\Coaster;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

#[AsEntityListener(event: Events::postPersist, method: 'invalidateSearchCache', entity: Coaster::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'invalidateSearchCache', entity: Coaster::class)]
#[AsEntityListener(event: Events::postRemove, method: 'invalidateSearchCache', entity: Coaster::class)]
class CoasterListener
{
    public function invalidateSearchCache(): void
    {
        $cache = new FilesystemAdapter();
        $cache->deleteItem(SearchController::CACHE_AUTOCOMPLETE);
    }
}
