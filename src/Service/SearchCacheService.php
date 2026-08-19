<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SearchCacheService
{
    private const int CACHE_TTL = 900; // 15 minutes
    private const string CACHE_PREFIX = 'search_';

    public function __construct(
        #[Autowire(service: 'search.cache_pool')]
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    /** @return array<string, mixed>|null */
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

    /** @param array<string, mixed> $results */
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
        $this->cache->clear();
    }

    private function getCacheKey(string $query): string
    {
        return self::CACHE_PREFIX.md5(strtolower(trim($query)));
    }
}
