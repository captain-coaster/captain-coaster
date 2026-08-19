<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SearchCacheService
{
    public function __construct(
        #[Autowire(service: 'search.cache_pool')]
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    public function invalidateSearchCache(): void
    {
        $this->cache->clear();
    }
}
