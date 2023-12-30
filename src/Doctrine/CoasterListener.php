<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Controller\SearchController;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CoasterListener
{
    /** @throws \Exception */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateCache();
    }

    /** @throws \Exception */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateCache();
    }

    /** @throws \Exception */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidateCache();
    }

    private function invalidateCache(): void
    {
        $cache = new FilesystemAdapter();
        $cache->deleteItem(SearchController::CACHE_AUTOCOMPLETE);
    }
}
