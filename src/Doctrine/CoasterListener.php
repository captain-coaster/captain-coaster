<?php

namespace App\Doctrine;

use App\Controller\SearchController;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Class ImageListener
 * @package App\Doctrine
 */
class CoasterListener
{
    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $this->invalidateCache();
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->invalidateCache();
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $this->invalidateCache();
    }

    private function invalidateCache()
    {
        $cache = new FilesystemAdapter();
        $cache->deleteItem(SearchController::CACHE_AUTOCOMPLETE);
    }
}
