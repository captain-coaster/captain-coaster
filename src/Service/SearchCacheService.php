<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class SearchCacheService
{
    private const int CACHE_TTL = 3600; // 1 hour
    private const string CACHE_PREFIX = 'search_';

    public function __construct(
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    public function getCachedResults(string $query): ?array
    {
        $cacheKey = $this->getCacheKey($query);

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            }

            return null;
        } catch (\Exception) {
            // If cache fails, return null to indicate cache miss
            return null;
        }
    }

    public function setCachedResults(string $query, array $results): void
    {
        $cacheKey = $this->getCacheKey($query);

        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($results);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        } catch (\Exception) {
            // If cache fails, silently continue without caching
        }
    }

    public function invalidateSearchCache(): void
    {
        if ($this->cache instanceof CacheItemPoolInterface) {
            $this->cache->clear();
        }
    }

    public function invalidateAutocompleteCache(): void
    {
        try {
            $this->cache->deleteItem('main_autocomplete');
        } catch (\Exception) {
            // If cache fails, silently continue
        }
    }

    public function invalidateCoasterCache(): void
    {
        try {
            $this->cache->deleteItem('coaster_search_all');
        } catch (\Exception) {
            // If cache fails, silently continue
        }
    }

    public function invalidateParkCache(): void
    {
        try {
            $this->cache->deleteItem('park_search_all');
        } catch (\Exception) {
            // If cache fails, silently continue
        }
    }

    public function warmCache(array $popularQueries): void
    {
        foreach ($popularQueries as $query) {
            $cacheKey = $this->getCacheKey($query);
            try {
                // Pre-warm cache with empty placeholder that will be filled by actual search
                $item = $this->cache->getItem($cacheKey);
                if (!$item->isHit()) {
                    $item->set([]);
                    $item->expiresAfter(self::CACHE_TTL);
                    $this->cache->save($item);
                }
            } catch (\Exception) {
                // If cache fails, silently continue
            }
        }
    }

    private function getCacheKey(string $query): string
    {
        // Replace colon with underscore to avoid reserved character issues
        return str_replace(':', '_', self::CACHE_PREFIX).md5(strtolower(trim($query)));
    }
}
