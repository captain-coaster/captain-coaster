<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SearchResponseDTO;
use App\DTO\SearchResultDTO;
use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class SearchService
{
    final public const array COASTER = [
        'emoji' => '🎢',
        'route' => 'redirect_coaster_show',
    ];

    final public const array PARK = [
        'emoji' => '🎡',
        'route' => 'redirect_park_show',
    ];

    final public const array USER = [
        'emoji' => '👦',
        'route' => 'user_show',
    ];

    /** SearchService constructor. */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SearchCacheService $cacheService
    ) {
    }

    /** Search across all entity types with caching support. */
    public function searchAll(string $query, int $limit = 5): SearchResponseDTO
    {
        $fromCache = false;
        $cacheKey = $this->getCacheKey($query);

        try {
            $cachedResults = $this->cacheService->getCachedResults($cacheKey);

            if (null !== $cachedResults) {
                $fromCache = true;
                error_log("🔥 REDIS CACHE HIT for query: '{$query}'");

                $response = new SearchResponseDTO(
                    $query,
                    $cachedResults['results'],
                    $cachedResults['totalResults'],
                    $cachedResults['hasMore']
                );

                // Add debug info to response
                $response->debug = ['source' => 'redis_cache', 'cache_key' => $cacheKey];

                return $response;
            } else {
                error_log("💾 REDIS CACHE MISS for query: '{$query}'");
            }
        } catch (\Exception $e) {
            // If caching fails, continue without cache
            error_log('Search cache error: '.$e->getMessage());
        }

        error_log("🔍 DATABASE QUERY for query: '{$query}'");
        $coasters = $this->searchCoasters($query, $limit);
        $parks = $this->searchParks($query, $limit);
        $users = $this->searchUsers($query, $limit);

        $results = [
            'coasters' => $coasters,
            'parks' => $parks,
            'users' => $users,
        ];

        $totalResults = [
            'coasters' => \count($coasters),
            'parks' => \count($parks),
            'users' => \count($users),
        ];

        $hasMore = \count($coasters) >= $limit || \count($parks) >= $limit || \count($users) >= $limit;

        $response = new SearchResponseDTO($query, $results, $totalResults, $hasMore);

        // Add debug info to response
        $response->debug = ['source' => 'database', 'cached' => false];

        try {
            // Cache the results
            $this->cacheService->setCachedResults($cacheKey, [
                'results' => $results,
                'totalResults' => $totalResults,
                'hasMore' => $hasMore,
            ]);
            error_log("💾 REDIS CACHE SET for query: '{$query}'");
        } catch (\Exception $e) {
            // If caching fails, continue without cache
            error_log('Search cache set error: '.$e->getMessage());
        }

        return $response;
    }

    /**
     * Search coasters with formatted results.
     *
     * @return array<int, SearchResultDTO>
     */
    public function searchCoasters(string $query, int $limit = 5): array
    {
        /** @var CoasterRepository $repository */
        $repository = $this->em->getRepository(Coaster::class);
        $results = $repository->findBySearchQuery($query, $limit);

        return $this->formatSearchResults($results, 'coaster');
    }

    /**
     * Search parks with formatted results.
     *
     * @return array<int, SearchResultDTO>
     */
    public function searchParks(string $query, int $limit = 5): array
    {
        /** @var ParkRepository $repository */
        $repository = $this->em->getRepository(Park::class);
        $results = $repository->findBySearchQuery($query, $limit);

        return $this->formatSearchResults($results, 'park');
    }

    /**
     * Search users with formatted results.
     *
     * @return array<int, SearchResultDTO>
     */
    public function searchUsers(string $query, int $limit = 5): array
    {
        /** @var UserRepository $repository */
        $repository = $this->em->getRepository(User::class);
        $results = $repository->findBySearchQuery($query, $limit);

        return $this->formatSearchResults($results, 'user');
    }

    /**
     * Format search results into SearchResultDTO objects.
     *
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<int, SearchResultDTO>
     */
    private function formatSearchResults(array $results, string $type): array
    {
        return array_map(function ($result) use ($type) {
            switch ($type) {
                case 'coaster':
                    return new SearchResultDTO(
                        id: $result['id'],
                        name: $result['name'],
                        slug: $result['slug'],
                        type: 'coaster',
                        image: null,
                        subtitle: $result['parkName'] ?? null,
                        metadata: [
                            'park' => $result['parkName'] ?? null,
                            'country' => $result['countryName'] ?? null,
                        ]
                    );
                case 'park':
                    return new SearchResultDTO(
                        id: $result['id'],
                        name: $result['name'],
                        slug: $result['slug'],
                        type: 'park',
                        subtitle: $result['countryName'] ?? null,
                        metadata: [
                            'country' => $result['countryName'] ?? null,
                        ]
                    );
                case 'user':
                    return new SearchResultDTO(
                        id: $result['id'],
                        name: $result['name'],
                        slug: $result['slug'],
                        type: 'user',
                        subtitle: \sprintf('%d ratings', $result['totalRatings'] ?? 0),
                        metadata: [
                            'totalRatings' => $result['totalRatings'] ?? 0,
                        ]
                    );
                default:
                    throw new \InvalidArgumentException("Unknown search result type: {$type}");
            }
        }, $results);
    }

    /**
     * Search all entities with pagination for comprehensive results page.
     *
     * @return array<string, mixed>
     */
    public function searchAllWithPagination(string $query, int $page = 1, int $perPage = 20): array
    {
        // Get all results without limit first to calculate totals
        $coasterResults = $this->searchCoastersUnlimited($query);
        $parkResults = $this->searchParksUnlimited($query);
        $userResults = $this->searchUsersUnlimited($query);

        // Combine all results into a single array with relevance scoring
        $allResults = [];

        // Add coasters with type info
        foreach ($coasterResults as $result) {
            $allResults[] = array_merge($result->toArray(), [
                'entity_type' => 'coaster',
                'emoji' => '🎢',
                'relevance_score' => $this->calculateRelevanceScore($result->name, $query),
            ]);
        }

        // Add parks with type info
        foreach ($parkResults as $result) {
            $allResults[] = array_merge($result->toArray(), [
                'entity_type' => 'park',
                'emoji' => '🎡',
                'relevance_score' => $this->calculateRelevanceScore($result->name, $query),
            ]);
        }

        // Add users with type info
        foreach ($userResults as $result) {
            $allResults[] = array_merge($result->toArray(), [
                'entity_type' => 'user',
                'emoji' => '👤',
                'relevance_score' => $this->calculateRelevanceScore($result->name, $query),
            ]);
        }

        // Sort by relevance score (higher is better)
        usort($allResults, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        $totalResults = \count($allResults);
        $totalPages = ceil($totalResults / $perPage);
        $offset = ($page - 1) * $perPage;

        // Get results for current page
        $paginatedResults = \array_slice($allResults, $offset, $perPage);

        return [
            'results' => $paginatedResults,
            'totalResults' => $totalResults,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'hasMore' => $page < $totalPages,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'per_page' => $perPage,
                'total_items' => $totalResults,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
                'previous' => $page > 1 ? $page - 1 : null,
                'next' => $page < $totalPages ? $page + 1 : null,
            ],
        ];
    }

    /**
     * Search coasters without limit for comprehensive results.
     *
     * @return array<int, SearchResultDTO>
     */
    private function searchCoastersUnlimited(string $query): array
    {
        /** @var CoasterRepository $repository */
        $repository = $this->em->getRepository(Coaster::class);
        $results = $repository->findBySearchQuery($query, 1000); // High limit for comprehensive search

        return $this->formatSearchResults($results, 'coaster');
    }

    /**
     * Search parks without limit for comprehensive results.
     *
     * @return array<int, SearchResultDTO>
     */
    private function searchParksUnlimited(string $query): array
    {
        /** @var ParkRepository $repository */
        $repository = $this->em->getRepository(Park::class);
        $results = $repository->findBySearchQuery($query, 1000); // High limit for comprehensive search

        return $this->formatSearchResults($results, 'park');
    }

    /**
     * Search users without limit for comprehensive results.
     *
     * @return array<int, SearchResultDTO>
     */
    private function searchUsersUnlimited(string $query): array
    {
        /** @var UserRepository $repository */
        $repository = $this->em->getRepository(User::class);
        $results = $repository->findBySearchQuery($query, 1000); // High limit for comprehensive search

        return $this->formatSearchResults($results, 'user');
    }

    /** Calculate relevance score for search results. */
    private function calculateRelevanceScore(string $name, string $query): float
    {
        $name = strtolower($name);
        $query = strtolower($query);

        // Exact match gets highest score
        if ($name === $query) {
            return 100.0;
        }

        // Starts with query gets high score
        if (str_starts_with($name, $query)) {
            return 90.0;
        }

        // Contains query as whole word gets medium-high score
        if (preg_match('/\b'.preg_quote($query, '/').'\b/', $name)) {
            return 80.0;
        }

        // Contains query anywhere gets medium score
        if (str_contains($name, $query)) {
            return 70.0;
        }

        // Fuzzy match based on similar_text
        $similarity = 0;
        similar_text($name, $query, $similarity);

        return $similarity;
    }

    /** Generate cache key for search query. */
    private function getCacheKey(string $query): string
    {
        return 'search_all_'.md5(strtolower(trim($query)));
    }
}
