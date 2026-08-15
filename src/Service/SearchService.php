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
        $cacheKey = $this->getCacheKey($query);

        try {
            $cachedResults = $this->cacheService->getCachedResults($cacheKey);

            if (null !== $cachedResults) {
                return new SearchResponseDTO(
                    $query,
                    $cachedResults['results'],
                    $cachedResults['totalResults'],
                    $cachedResults['hasMore']
                );
            }
        } catch (\Exception) {
            // If caching fails, continue without cache
        }

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

        try {
            // Cache the results
            $this->cacheService->setCachedResults($cacheKey, [
                'results' => $results,
                'totalResults' => $totalResults,
                'hasMore' => $hasMore,
            ]);
        } catch (\Exception) {
            // If caching fails, continue without cache
        }

        return $response;
    }

    /**
     * Return recent and upcoming coaster names for search placeholder rotation.
     *
     * @return array<int, string>
     */
    public function searchRecent(): array
    {
        /** @var CoasterRepository $repository */
        $repository = $this->em->getRepository(Coaster::class);

        return $repository->findRecentForPlaceholder();
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
        $results = $this->attachParkImages($results);

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
        return array_map(static function ($result) use ($type) {
            switch ($type) {
                case 'coaster':
                    return new SearchResultDTO(
                        id: $result['id'],
                        name: $result['name'],
                        slug: $result['slug'],
                        type: 'coaster',
                        image: $result['imagePath'] ?? null,
                        subtitle: $result['parkName'] ?? null,
                        metadata: [
                            'park' => $result['parkName'] ?? null,
                            'country' => $result['countryName'] ?? null,
                            'rank' => $result['rank'] ?? null,
                            'score' => $result['score'] ?? null,
                            'totalRatings' => $result['totalRatings'] ?? 0,
                            'status' => $result['statusName'] ?? null,
                        ]
                    );
                case 'park':
                    return new SearchResultDTO(
                        id: $result['id'],
                        name: $result['name'],
                        slug: $result['slug'],
                        type: 'park',
                        image: $result['imagePath'] ?? null,
                        subtitle: $result['countryName'] ?? null,
                        metadata: [
                            'country' => $result['countryName'] ?? null,
                            'coasterCount' => $result['coasterCount'] ?? 0,
                        ]
                    );
                case 'user':
                    return new SearchResultDTO(
                        id: $result['id'],
                        name: $result['name'],
                        slug: $result['slug'],
                        type: 'user',
                        image: $result['profilePicture'] ?? null,
                        subtitle: null,
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
    /** @return array<string, mixed> */
    public function searchAllWithPagination(string $query, int $page = 1, int $perPage = 20, string $typeFilter = 'all'): array
    {
        $coasterResults = $this->searchCoastersUnlimited($query);
        $parkResults = $this->searchParksUnlimited($query);
        $userResults = $this->searchUsersUnlimited($query);

        $allResults = [];

        foreach ($coasterResults as $result) {
            $allResults[] = array_merge($result->toArray(), [
                'entity_type' => 'coaster',
                'relevance_score' => $this->calculateRelevanceScore($result->name, $query),
            ]);
        }

        foreach ($parkResults as $result) {
            $allResults[] = array_merge($result->toArray(), [
                'entity_type' => 'park',
                'relevance_score' => $this->calculateRelevanceScore($result->name, $query),
            ]);
        }

        foreach ($userResults as $result) {
            $allResults[] = array_merge($result->toArray(), [
                'entity_type' => 'user',
                'relevance_score' => $this->calculateRelevanceScore($result->name, $query),
            ]);
        }

        usort($allResults, static fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        // Count by type before filtering (always shows full counts in tabs)
        $countByType = [
            'all' => \count($allResults),
            'coaster' => \count($coasterResults),
            'park' => \count($parkResults),
            'user' => \count($userResults),
        ];

        // Filter by type before pagination so page slices are correct
        if ('all' !== $typeFilter) {
            $allResults = array_values(array_filter(
                $allResults,
                static fn ($r) => $r['entity_type'] === $typeFilter
            ));
        }

        $totalResults = \count($allResults);
        $totalPages = (int) ceil($totalResults / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = \array_slice($allResults, $offset, $perPage);

        return [
            'results' => $paginatedResults,
            'totalResults' => $totalResults,
            'countByType' => $countByType,
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
        $results = $repository->findBySearchQuery($query, 1000);
        $results = $this->attachParkImages($results);

        return $this->formatSearchResults($results, 'park');
    }

    /**
     * Attach best-ranked coaster image to each park result row.
     *
     * @param array<int, array<string, mixed>> $parkRows
     *
     * @return array<int, array<string, mixed>>
     */
    private function attachParkImages(array $parkRows): array
    {
        if (empty($parkRows)) {
            return $parkRows;
        }

        $parkIds = array_column($parkRows, 'id');

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.park) as parkId, img.filename as imageFilename')
            ->from(Coaster::class, 'c')
            ->join('c.mainImage', 'img')
            ->where('c.park IN (:parkIds)')
            ->andWhere('c.rank IS NOT NULL')
            ->setParameter('parkIds', $parkIds)
            ->orderBy('c.rank', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $imageByPark = [];
        foreach ($rows as $row) {
            $pid = (int) $row['parkId'];
            if (!isset($imageByPark[$pid])) {
                $imageByPark[$pid] = (string) $row['imageFilename'];
            }
        }

        foreach ($parkRows as &$row) {
            $row['imagePath'] = $imageByPark[(int) $row['id']] ?? null;
        }

        return $parkRows;
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
