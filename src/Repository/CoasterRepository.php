<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Coaster>
 */
class CoasterRepository extends ServiceEntityRepository
{
    // The ranking is recomputed monthly; the unfiltered listing is cached for a
    // month and explicitly invalidated when a new ranking is published.
    private const int RANKING_CACHE_TTL = 35 * 86400;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coaster::class);
    }

    /**
     * Find a random coaster from the top N ranked coasters that have a main image.
     * Used for the auth page background image.
     */
    public function findRandomTopRanked(int $limit = 10): ?Coaster
    {
        $results = $this->createQueryBuilder('c')
            ->join('c.mainImage', 'i')
            ->where('c.rank IS NOT NULL')
            ->andWhere('c.rank <= :topN')
            ->setParameter('topN', $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $results ? $results[array_rand($results)] : null;
    }

    /**
     * Get distinct opening years for filter dropdown.
     *
     * @return array<array{year: int}> Array of years
     */
    public function findDistinctOpeningYears(): array
    {
        $rsm = new Query\ResultSetMapping();
        $rsm->addScalarResult('year', 'year');

        return $this->getEntityManager()
            ->createNativeQuery(
                'SELECT DISTINCT YEAR(c.openingDate) as year FROM coaster c WHERE c.openingDate IS NOT NULL ORDER BY year DESC',
                $rsm
            )
            ->getResult();
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestCoasterForTop(string $term, User $user): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id', 'c.name as coaster', 'p.name as park', 'r.rating as rating', 'i.filename as image')
            ->from(Coaster::class, 'c')
            ->join('c.park', 'p')
            ->leftJoin('c.ratings', 'r', Expr\Join::WITH, 'r.user = :user')
            ->leftJoin('c.mainImage', 'i')
            ->where('c.name LIKE :term')
            ->orWhere('p.name LIKE :term')
            ->orWhere('c.slug LIKE :term2')
            ->orWhere('p.slug LIKE :term2')
            ->setParameter('term', \sprintf('%%%s%%', $term))
            ->setParameter('term2', str_replace(' ', '-', \sprintf('%%%s%%', $term)))
            ->setParameter('user', $user)
            ->setMaxResults(15)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find coasters for a specific park (used in map popups).
     * Returns array of coaster entities.
     * Expects filters to already be validated and authorized.
     *
     * @param Park                 $park    The park to get coasters for
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return array<Coaster> Array of coaster entities
     */
    public function findForPark(Park $park, array $filters = []): array
    {
        $qb = $this->createBaseQuery()
            ->select('c', 's', 'p')
            ->where('p.id = :parkId')
            ->setParameter('parkId', $park->getId());

        $this->applyFilters($qb, $filters, 'map');

        return $qb->getQuery()->getResult();
    }

    /**
     * Optimized search method for API with limited results and better performance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBySearchQuery(string $query, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->select(
                'c.id',
                'c.name',
                'c.slug',
                'p.name as parkName',
                'co.name as countryName',
                'c.rank',
                'c.score',
                'c.totalRatings',
                's.name as statusName',
                'i.filename as imagePath'
            )
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'co')
            ->leftJoin('c.status', 's')
            ->leftJoin('c.mainImage', 'i')
            ->where('c.name LIKE :query OR c.slug LIKE :slugQuery')
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('slugQuery', '%'.str_replace(' ', '-', $query).'%')
            ->orderBy('c.score', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(300) // Cache for 5 minutes
            ->getArrayResult();
    }

    /** Find a newly ranked coaster to add in neach month notification */
    public function getNewlyRankedHighlightedCoaster(int $maxRank = 300): ?Coaster
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->andWhere('c.previousRank is null')
            ->andWhere('c.rank < :maxRank')
            ->setParameter('maxRank', $maxRank)
            ->orderBy('c.rank', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, Coaster> */
    public function findAllCoastersInPark(Park $park): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.status', 's')
            ->andWhere('c.park = :park')
            ->setParameter('park', $park)
            ->orderBy('s.order', 'ASC')
            ->addOrderBy('c.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find coasters for search page.
     * Returns Query object for pagination.
     * Expects filters to already be validated and authorized.
     *
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return Query<mixed, mixed>
     */
    public function findForSearch(array $filters = []): Query
    {
        $qb = $this->createBaseQuery()->select('c');
        $this->applyFilters($qb, $filters, 'search');

        // Sort by distance if coordinates provided, otherwise by updatedAt
        if ($this->hasValidCoordinates($filters)) {
            $this->applyDistanceSort($qb, $filters['latitude'], $filters['longitude']);
        } else {
            $qb->orderBy('c.updatedAt', 'DESC');
        }

        return $qb->getQuery();
    }

    /**
     * Find coasters for ranking page.
     * Returns Query object for pagination.
     * Expects filters to already be validated and authorized.
     *
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return Query<mixed, mixed>
     */
    public function findForRanking(array $filters = []): Query
    {
        $qb = $this->createBaseQuery()
            ->select('c', 'p', 'm')
            ->andWhere('c.rank IS NOT NULL');

        $this->applyFilters($qb, $filters, 'ranking');
        $qb->orderBy('c.rank', 'ASC');

        $query = $qb->getQuery();

        // ridden / notridden are the only filters that make the result set
        // user-specific; with those active the cache must not be shared. A bare
        // 'user' param (always sent by logged-in users) doesn't change the rows,
        // so those requests can still share the public cache.
        $isPersonalized = (isset($filters['ridden']) && 'on' === $filters['ridden'])
            || (isset($filters['notridden']) && 'on' === $filters['notridden']);
        if ($isPersonalized) {
            return $query;
        }

        // The ranking only changes on the monthly recompute, so the canonical
        // unfiltered listing is cached for a full month and invalidated when
        // RankingService publishes a new ranking (it clears the result-cache pool).
        // Filtered public listings keep a short TTL — many combinations, low hit rate.
        $isUnfiltered = [] === array_diff_key($filters, ['user' => null]);
        $query->enableResultCache($isUnfiltered ? self::RANKING_CACHE_TTL : 300);

        return $query;
    }

    /**
     * Bounds of the "top 100" progress cohort: the 100 best-ranked coasters that
     * are not definitively closed (demolished). Temporarily-closed ones stay in —
     * they can reopen. Demolished coasters are kept in the ranking itself, but a
     * user can never ride them, so they're excluded here to keep 100% reachable.
     *
     * Returns the cohort size (≤ 100) and the rank of its last member, so a
     * per-user "ridden" count can be derived with a simple rank threshold.
     *
     * @return array{size: int, cutoffRank: int}
     */
    public function findTop100CohortBounds(): array
    {
        $query = $this->createQueryBuilder('c')
            ->select('c.rank')
            ->innerJoin('c.status', 's')
            ->where('c.rank IS NOT NULL')
            ->andWhere('s.name != :defunct')
            ->setParameter('defunct', Status::CLOSED_DEFINITELY)
            ->orderBy('c.rank', 'ASC')
            ->setMaxResults(100)
            ->getQuery();
        // Stable for the month — cleared by RankingService on publish.
        $query->enableResultCache(self::RANKING_CACHE_TTL);

        /** @var array<int, int> $ranks */
        $ranks = $query->getSingleColumnResult();

        return [
            'size' => \count($ranks),
            'cutoffRank' => $ranks ? (int) end($ranks) : 0,
        ];
    }

    /** @return array<int, Coaster> */
    public function getTopRanked(int $limit = 3): array
    {
        $query = $this->createQueryBuilder('c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'i')
            ->where('c.rank IS NOT NULL')
            ->orderBy('c.rank', 'ASC')
            ->setMaxResults($limit)
            ->getQuery();

        // Named cache key so RankingService can invalidate it after each monthly update.
        $query->enableResultCache(86400, 'homepage_top_ranked'); // 24h default, invalidated on ranking update

        return $query->getResult();
    }

    /**
     * Find recent and upcoming coaster names for search placeholder rotation.
     *
     * @return array<int, string>
     */
    public function findRecentForPlaceholder(): array
    {
        $now = new \DateTimeImmutable();
        $minDate = $now->modify('-180 days')->format('Y-m-d');
        $maxDate = $now->modify('+180 days')->format('Y-m-d');

        $query = $this->createQueryBuilder('c')
            ->select('c.name')
            ->innerJoin('c.status', 's')
            ->where('c.openingDate BETWEEN :minDate AND :maxDate')
            ->andWhere('s.name IN (:statuses)')
            ->setParameter('minDate', $minDate)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('statuses', [Status::OPERATING, Status::CLOSED_TEMPORARILY, 'status.construction', 'status.announced', 'status.soft.opening'])
            ->orderBy('c.openingDate', 'DESC')
            ->setMaxResults(8)
            ->getQuery();
        $query->enableResultCache(3600);

        return array_column($query->getArrayResult(), 'name');
    }

    /**
     * Find a recently opened coaster (window: -60 days to today).
     * Used for the homepage hero.
     */
    public function findRecentlyOpenedCoaster(): ?Coaster
    {
        $now = new \DateTimeImmutable();
        $today = $now->format('Y-m-d');
        $minDate = $now->modify('-60 days')->format('Y-m-d');

        $query = $this->createQueryBuilder('c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'i')
            ->where('c.openingDate BETWEEN :minDate AND :today')
            ->andWhere('c.mainImage IS NOT NULL')
            ->setParameter('minDate', $minDate)
            ->setParameter('today', $today)
            ->orderBy('c.totalRatings', 'DESC')
            ->addOrderBy('c.openingDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery();
        $query->enableResultCache(86400); // 24h — new openings don't change by the hour

        return $query->getOneOrNullResult();
    }

    /**
     * Find an upcoming coaster (window: tomorrow to +90 days).
     * Used for the homepage hero.
     */
    public function findUpcomingCoaster(): ?Coaster
    {
        $now = new \DateTimeImmutable();
        $today = $now->format('Y-m-d');
        $maxDate = $now->modify('+90 days')->format('Y-m-d');

        $query = $this->createQueryBuilder('c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'i')
            ->where('c.openingDate > :today')
            ->andWhere('c.openingDate <= :maxDate')
            ->andWhere('c.mainImage IS NOT NULL')
            ->setParameter('today', $today)
            ->setParameter('maxDate', $maxDate)
            ->orderBy('c.openingDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery();
        $query->enableResultCache(86400); // 24h — upcoming dates don't change by the hour

        return $query->getOneOrNullResult();
    }

    /**
     * Find the coaster with the most new ratings in the last 12 hours.
     * Used for the homepage "Trending" widget — hot branch (fallback when no novelty).
     *
     * @param array<int> $excludeIds Coaster IDs to exclude (eg. already shown in top ranked)
     */
    public function findTrendingCoaster(array $excludeIds = []): ?Coaster
    {
        $sinceDate = new \DateTimeImmutable('-12 hours');

        $qb = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(r.id) AS HIDDEN recentRatingCount')
            ->leftJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'i')
            ->innerJoin(RiddenCoaster::class, 'r', Expr\Join::WITH, 'r.coaster = c.id AND r.createdAt >= :sinceDate')
            ->andWhere('c.mainImage IS NOT NULL')
            ->setParameter('sinceDate', $sinceDate)
            ->groupBy('c.id')
            ->having('COUNT(r.id) >= 3') // lower threshold for 12h window
            ->orderBy('recentRatingCount', 'DESC')
            ->setMaxResults(1);

        if (!empty($excludeIds)) {
            $qb->andWhere('c.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeIds);
        }

        $query = $qb->getQuery();
        $query->enableResultCache(43200); // 12h

        return $query->getOneOrNullResult();
    }

    /**
     * Find operating coasters within a given distance from arbitrary coordinates.
     * Each row is a single coaster (with its parent park's distance).
     * Used for the homepage geolocation widget.
     *
     * @return array<int, array{id: int, name: string, slug: string, parkName: string, parkSlug: string, countryName: ?string, distance: int, mainImage: ?string, rank: ?int, score: ?float}>
     */
    public function findNearbyCoasters(float $latitude, float $longitude, int $maxDistance = 200, int $limit = 5): array
    {
        // Bounding box for index pre-filter (rough — 1 deg ≈ 111km)
        $degreeBuffer = max(2.0, $maxDistance / 50.0);

        /** @var array<int, array{id: int|string, name: string, slug: string, parkName: string, parkSlug: string, countryName: ?string, distance: float|string, mainImage: ?string, rank: int|string|null, score: float|string|null}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('c.id as id, c.name as name, c.slug as slug, c.rank as rank, c.score as score')
            ->addSelect('p.name as parkName, p.slug as parkSlug')
            ->addSelect('co.name as countryName')
            ->addSelect('img.filename as mainImage')
            ->addSelect('ROUND(( 6371 * acos( cos( radians(:lat) )
              * cos( radians( p.latitude ) )
              * cos( radians( p.longitude ) - radians(:lng) )
              + sin( radians(:lat) )
              * sin( radians( p.latitude ) ) ) ) ) AS distance')
            ->innerJoin('c.park', 'p')
            ->innerJoin('c.status', 's', 'WITH', 's.name = :operating')
            ->leftJoin('p.country', 'co')
            ->leftJoin('c.mainImage', 'img')
            ->where('c.enabled = true')
            ->andWhere('c.mainImage IS NOT NULL')
            ->andWhere('p.latitude BETWEEN :latMin AND :latMax')
            ->andWhere('p.longitude BETWEEN :lngMin AND :lngMax')
            ->having('distance <= :maxDistance')
            ->orderBy('distance', 'ASC')
            ->addOrderBy('c.rank', 'ASC')
            ->setParameter('lat', $latitude)
            ->setParameter('lng', $longitude)
            ->setParameter('latMin', $latitude - $degreeBuffer)
            ->setParameter('latMax', $latitude + $degreeBuffer)
            ->setParameter('lngMin', $longitude - $degreeBuffer)
            ->setParameter('lngMax', $longitude + $degreeBuffer)
            ->setParameter('maxDistance', $maxDistance)
            ->setParameter('operating', Status::OPERATING)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'parkName' => (string) $row['parkName'],
            'parkSlug' => (string) $row['parkSlug'],
            'countryName' => $row['countryName'] ?? null,
            'distance' => (int) $row['distance'],
            'mainImage' => $row['mainImage'] ?? null,
            'rank' => isset($row['rank']) ? (int) $row['rank'] : null,
            'score' => isset($row['score']) ? (float) $row['score'] : null,
        ], $rows);
    }

    /**
     * Find map markers for all parks with coasters.
     * Returns array of marker data (not Query object).
     * Expects filters to already be validated and authorized.
     *
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return array<array{name: string, latitude: float, longitude: float, nb: int, id: int}> Array of marker data
     */
    public function findForMap(array $filters = []): array
    {
        $qb = $this->createBaseQuery()
            ->select('p.name as name, p.latitude as latitude, p.longitude as longitude, count(1) as nb, p.id as id')
            ->andWhere('p.latitude IS NOT NULL')
            ->andWhere('p.longitude IS NOT NULL')
            ->groupBy('c.park');

        $this->applyFilters($qb, $filters, 'map');

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Find top N coasters by a numeric metric (height, length, speed, inversionsNumber).
     *
     * Considers only coasters that are operating or temporarily closed,
     * and applies an optional continent filter (by slug).
     *
     * Rows are returned in strict metric-DESC order (ties broken by id ASC) so the
     * caller can compute competition ranking (ties sharing a rank) from the result.
     *
     * @param string      $metric        One of: 'height', 'length', 'speed', 'inversionsNumber'
     * @param int         $limit         Number of rows to fetch
     * @param string|null $continentSlug Continent slug, or null for World (no filter)
     *
     * @return array<int, Coaster>
     */
    public function findTopByMetric(string $metric, int $limit = 3, ?string $continentSlug = null): array
    {
        $allowedMetrics = ['height', 'length', 'speed', 'inversionsNumber'];
        if (!\in_array($metric, $allowedMetrics, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid metric "%s". Allowed: %s.', $metric, implode(', ', $allowedMetrics)));
        }

        $qb = $this->createQueryBuilder('c')
            ->select('c', 'p', 'mainImage', 's')
            ->innerJoin('c.park', 'p')
            ->innerJoin('c.status', 's')
            ->leftJoin('c.mainImage', 'mainImage')
            ->where('c.enabled = :enabled')
            ->andWhere('s.name IN (:openStatuses)')
            ->andWhere(\sprintf('c.%s IS NOT NULL', $metric))
            ->andWhere(\sprintf('c.%s > 0', $metric))
            ->setParameter('enabled', true)
            ->setParameter('openStatuses', [Status::OPERATING, Status::CLOSED_TEMPORARILY])
            ->orderBy(\sprintf('c.%s', $metric), 'DESC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $continentSlug && '' !== $continentSlug) {
            $qb->innerJoin('p.country', 'country')
                ->innerJoin('country.continent', 'continent')
                ->andWhere('continent.slug = :continentSlug')
                ->setParameter('continentSlug', $continentSlug);
        }

        $query = $qb->getQuery();
        $query->enableResultCache(3600); // 1h — records don't change often

        return $query->getResult();
    }

    private function createBaseQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'country')
            ->leftJoin('country.continent', 'continent')
            ->leftJoin('c.manufacturer', 'm')
            ->leftJoin('c.materialType', 'mt')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.model', 'model')
            ->leftJoin('c.status', 's');
    }

    /**
     * Orchestrates all filter groups and applies them to the query builder.
     * Expects filters to already be validated and sanitized.
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Validated and sanitized filter array
     * @param string               $context Context where filters are applied (ranking, search, map)
     */
    private function applyFilters(QueryBuilder $qb, array $filters, string $context = 'search'): void
    {
        // Apply filters in logical groups
        $this->applyLocationFilters($qb, $filters);
        $this->applyCharacteristicFilters($qb, $filters);
        $this->applyStatusFilters($qb, $filters);
        $this->applyUserFilters($qb, $filters);

        // Apply context-specific filters
        if ('ranking' === $context) {
            $this->applyRankingFilters($qb, $filters);
        }
    }

    /**
     * Applies location-based filters (continent, country).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyLocationFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['continent'])) {
            $qb->andWhere('continent.id = :continent')
               ->setParameter('continent', $filters['continent']);
        }

        if (!empty($filters['country'])) {
            $qb->andWhere('country.id = :country')
               ->setParameter('country', $filters['country']);
        }
    }

    /**
     * Applies coaster characteristic filters (manufacturer, material, seating, model, opening date, name, score).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyCharacteristicFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['manufacturer'])) {
            $qb->andWhere('m.id = :manufacturer')
               ->setParameter('manufacturer', $filters['manufacturer']);
        }

        if (!empty($filters['materialType'])) {
            $qb->andWhere('mt.id = :materialType')
               ->setParameter('materialType', $filters['materialType']);
        }

        if (!empty($filters['seatingType'])) {
            $qb->andWhere('st.id = :seatingType')
               ->setParameter('seatingType', $filters['seatingType']);
        }

        if (!empty($filters['model'])) {
            $qb->andWhere('model.id = :model')
               ->setParameter('model', $filters['model']);
        }

        if (!empty($filters['openingDate'])) {
            $year = $filters['openingDate'];
            $qb->andWhere('c.openingDate BETWEEN :yearStart AND :yearEnd')
               ->setParameter('yearStart', $year.'-01-01')
               ->setParameter('yearEnd', $year.'-12-31');
        }

        if (!empty($filters['name'])) {
            $qb->andWhere('c.name LIKE :name')
               ->setParameter('name', '%'.$filters['name'].'%');
        }

        if (!empty($filters['score'])) {
            $qb->andWhere('c.score >= :score')
               ->setParameter('score', $filters['score']);
        }
    }

    /**
     * Applies status-based filters (operating status, kiddie).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyStatusFilters(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['status']) && 'on' === $filters['status']) {
            $qb->andWhere('s.name = :operating')
               ->setParameter('operating', Status::OPERATING);
        }

        if (isset($filters['kiddie'])) {
            $qb->andWhere('c.kiddie = :kiddie')
               ->setParameter('kiddie', 'on' !== $filters['kiddie']);
        }
    }

    /**
     * Applies user-specific filters (ridden, not ridden).
     * Expects permissions to already be checked.
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Validated and authorized filter array
     */
    private function applyUserFilters(QueryBuilder $qb, array $filters): void
    {
        if (empty($filters['user'])) {
            return;
        }

        $userId = $filters['user'];

        if (isset($filters['ridden']) && 'on' === $filters['ridden']) {
            $subQuery = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('rc_c.id')
                ->from(RiddenCoaster::class, 'rc')
                ->innerJoin('rc.coaster', 'rc_c')
                ->where('rc.user = :userId');

            $qb->andWhere($qb->expr()->in('c.id', $subQuery->getDQL()))
               ->setParameter('userId', $userId);
        }

        if (isset($filters['notridden']) && 'on' === $filters['notridden']) {
            $subQuery = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('rc_c.id')
                ->from(RiddenCoaster::class, 'rc')
                ->innerJoin('rc.coaster', 'rc_c')
                ->where('rc.user = :userId');

            $qb->andWhere($qb->expr()->notIn('c.id', $subQuery->getDQL()))
               ->setParameter('userId', $userId);
        }
    }

    /**
     * Applies ranking-specific filters (new coasters in ranking).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyRankingFilters(QueryBuilder $qb, array $filters): void
    {
        // 'new' filter: coasters new to ranking this month
        if (isset($filters['new']) && 'on' === $filters['new']) {
            $qb->andWhere('c.previousRank IS NULL')
               ->andWhere('c.rank IS NOT NULL');
        }
    }

    /**
     * Find all enabled coasters with minimum number of reviews for AI summary generation.
     *
     * @param int      $minReviews Minimum number of reviews required
     * @param int|null $limit      Optional limit on results
     *
     * @return array<Coaster> Array of coaster entities ordered by ID
     */
    public function findEligibleForSummary(int $minReviews, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c')
            ->leftJoin('c.ratings', 'rc')
            ->leftJoin('rc.user', 'u')
            ->where('c.enabled = :enabled')
            ->andWhere('rc.review IS NOT NULL')
            ->andWhere('TRIM(rc.review) != \'\'')
            ->andWhere('u.enabled = 1')
            ->groupBy('c.id')
            ->having('COUNT(rc.id) >= :minReviews')
            ->orderBy('c.id', 'ASC')
            ->setParameter('enabled', true)
            ->setParameter('minReviews', $minReviews);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if filters contain valid coordinates for distance sorting.
     *
     * @param array<string, mixed> $filters Validated filter array
     */
    private function hasValidCoordinates(array $filters): bool
    {
        return isset($filters['sortByDistance'], $filters['latitude'], $filters['longitude'])
            && 'on' === $filters['sortByDistance']
            && is_numeric($filters['latitude'])
            && is_numeric($filters['longitude']);
    }

    /**
     * Apply distance-based sorting using Haversine formula.
     * Uses native SQL for performance with large datasets.
     *
     * @param QueryBuilder $qb        The query builder to modify
     * @param float        $latitude  User's latitude
     * @param float        $longitude User's longitude
     */
    private function applyDistanceSort(QueryBuilder $qb, float $latitude, float $longitude): void
    {
        // Haversine formula for distance calculation (in km)
        // Using a simplified version that works well for sorting purposes
        $qb->addSelect(
            '(6371 * ACOS(
                COS(RADIANS(:userLat)) * COS(RADIANS(p.latitude)) *
                COS(RADIANS(p.longitude) - RADIANS(:userLng)) +
                SIN(RADIANS(:userLat)) * SIN(RADIANS(p.latitude))
            )) AS HIDDEN distance'
        )
            ->andWhere('p.latitude IS NOT NULL')
            ->andWhere('p.longitude IS NOT NULL')
            ->setParameter('userLat', $latitude)
            ->setParameter('userLng', $longitude)
            ->orderBy('distance', 'ASC');
    }
}
