<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends ServiceEntityRepository<RiddenCoaster>
 */
class RiddenCoasterRepository extends ServiceEntityRepository
{
    private TranslatorInterface $translatorInterface;

    public function __construct(ManagerRegistry $registry, TranslatorInterface $translatorInterface)
    {
        parent::__construct($registry, RiddenCoaster::class);
        $this->translatorInterface = $translatorInterface;
    }

    /** Count all ratings. */
    public function countAll(): int
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_rating')
                ->from(RiddenCoaster::class, 'r')
                ->where('r.rating IS NOT NULL')
                ->getQuery();

            $query->enableResultCache(600);

            return (int) $query->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * Count all ratings with text review.
     *
     * @throws NonUniqueResultException
     */
    public function countReviews(): int
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(r.review) as nb_review')
            ->from(RiddenCoaster::class, 'r')
            ->getQuery();

        $query->enableResultCache(600);

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Count all new ratings since date passed in parameter.
     *
     * @throws NonUniqueResultException
     */
    public function countNew(\DateTime $date): int
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.createdAt > :date')
            ->setParameter('date', $date)
            ->getQuery();

        $query->enableResultCache(600);

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Count all ratings for a specific user passed in parameter.
     *
     * @throws NonUniqueResultException
     */
    public function countForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForCoaster(Coaster $coaster): ?int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(RiddenCoaster::class, 'r')
                ->where('r.coaster = :coaster')
                ->setParameter('coaster', $coaster)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    /**
     * Get ratings for a specific coaster.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<int, RiddenCoaster>
     */
    public function getCoasterReviews(
        Coaster $coaster,
        string $locale = 'en',
        bool $displayReviewsInAllLanguages = true,
        array $filters = []
    ): array {
        // add joins to avoid multiple subqueries
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'p', 'c', 'u', 'up', 'co')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->leftJoin('r.pros', 'p')
            ->leftjoin('r.cons', 'c')
            ->leftJoin('r.upvotes', 'up')
            ->leftJoin('r.coaster', 'co')
            ->where('r.coaster = :coasterId')
            ->andWhere('u.enabled = 1')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages);

        $this->applyFilters($query, $filters);

        return $query->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(QueryBuilder $query, array $filters): void
    {
        // Sorting
        $this->sort($query, $filters);
    }

    /** @param array<string, mixed> $filters */
    private function sort(QueryBuilder $query, array $filters): void
    {
        // Map of allowed sort keys (URL/UI value) to DQL field names
        $sortingOptions = [
            'value' => 'r.rating',
            'rating' => 'r.rating',
            'updatedAt' => 'r.updatedAt',
        ];

        if (\array_key_exists('sort', $filters) && '' !== $filters['sort'] && str_contains($filters['sort'], '|')) {
            $sort = explode('|', $filters['sort']);

            if (!\array_key_exists($sort[0], $sortingOptions) || !\in_array($sort[1], ['ASC', 'DESC', 'asc', 'desc'])) {
                $this->defaultSort($query);
            } else {
                $query->addOrderBy($sortingOptions[$sort[0]], $sort[1]);
            }
        } else {
            $this->defaultSort($query);
        }
    }

    /**
     * Default sort: prioritizes reviews with text in user's language first,
     * then sorts by review score (upvotes - downvotes), then by date.
     * This ensures users see relevant, high-quality content at the top.
     */
    private function defaultSort(QueryBuilder $query): void
    {
        $query
            ->addOrderBy('languagePriority', 'ASC')
            ->addOrderBy('r.score', 'DESC')
            ->addOrderBy('r.updatedAt', 'DESC');
    }

    /**
     * Get only reviews with text content for a specific coaster (all languages).
     * Returns RiddenCoaster entities with review text and rating values.
     *
     * @return array<int, RiddenCoaster>
     */
    public function getCoasterReviewsWithText(Coaster $coaster, ?int $limit = null): array
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :coasterId')
            ->andWhere('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'desc')
            ->setParameter('coasterId', $coaster->getId());

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /** Count reviews with text content for a specific coaster. */
    public function countCoasterReviewsWithText(Coaster $coaster): int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(r.id)')
                ->from(RiddenCoaster::class, 'r')
                ->innerJoin('r.user', 'u')
                ->where('r.coaster = :coasterId')
                ->andWhere('r.review IS NOT NULL')
                ->andWhere('TRIM(r.review) != \'\'')
                ->andWhere('u.enabled = 1')
                ->setParameter('coasterId', $coaster->getId())
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Get latest text reviews ordered by language.
     *
     * @return array<int, RiddenCoaster>
     */
    public function getLatestReviews(string $locale = 'en', int $limit = 3, bool $displayReviewsInAllLanguages = false): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->addSelect('u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.review is not null')
            ->andWhere('u.enabled = 1')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setMaxResults($limit)
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages)
            ->getQuery();

        $query->enableResultCache(300);

        return $query->getResult();
    }

    /**
     * Get a random selection of recent, community-validated reviews for the homepage.
     *
     * Algorithm:
     *   - Fetch a pool of 20 qualifying reviews (upvoteCounter >= 3, last 90 days)
     *   - Shuffle and return $limit — changes on every page load, no two visits show the same set
     *   - Language priority: user's locale first, then others
     *
     * @return array<int, RiddenCoaster>
     */
    public function getLatestLikedReviews(string $locale = 'en', int $limit = 3, bool $displayReviewsInAllLanguages = false): array
    {
        $minUpvotes = 3;
        $sinceDate = new \DateTime('-90 days');
        $poolSize = 20;

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->addSelect('u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.review IS NOT NULL')
            ->andWhere('u.enabled = 1')
            ->andWhere('r.upvoteCounter >= :minUpvotes')
            ->andWhere('r.updatedAt >= :sinceDate')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.upvoteCounter', 'desc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setMaxResults($poolSize)
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages)
            ->setParameter('minUpvotes', $minUpvotes)
            ->setParameter('sinceDate', $sinceDate)
            ->getQuery();

        $query->enableResultCache(3600); // 1h — pool is stable, randomisation is PHP-side

        /** @var array<int, RiddenCoaster> $pool */
        $pool = $query->getResult();

        if (\count($pool) <= $limit) {
            return $pool;
        }

        // Random selection from the pool — different on every request.
        $keys = array_rand($pool, $limit);
        if (!\is_array($keys)) {
            $keys = [$keys];
        }

        return array_values(array_intersect_key($pool, array_flip($keys)));
    }

    /**
     * Get latest ratings from enabled users only.
     *
     * @return array<int, RiddenCoaster>
     */
    public function getLatestRatings(int $limit = 6): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('u.enabled = 1')
            ->andWhere('r.rating IS NOT NULL')
            ->orderBy('r.updatedAt', 'desc')
            ->setMaxResults($limit)
            ->getQuery();

        $query->enableResultCache(300);

        return $query->getResult();
    }

    /** @return QueryBuilder */
    public function getUserRatings(User $user, ?string $search = null)
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'm', 'c', 'p', 's')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.user', 'u')
            ->join('r.coaster', 'c')
            ->join('c.status', 's')
            ->leftJoin('c.manufacturer', 'm')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('c.name LIKE :search OR p.name LIKE :search')
               ->setParameter('search', '%'.addcslashes($search, '%_\\').'%');
        }

        return $qb;
    }

    /** @return QueryBuilder */
    public function getUserReviews(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'm', 'c', 'p')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.user', 'u')
            ->join('r.coaster', 'c')
            ->leftJoin('c.manufacturer', 'm')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->andWhere('r.review is not null')
            ->setParameter('user', $user);
    }

    /**
     * Get all reviews ordered by language.
     *
     * @return array|mixed
     *
     * @throws NonUniqueResultException
     */
    public function findAllReviews(string $locale = 'en', bool $displayReviewsInAllLanguages = false)
    {
        $count = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.review is not null')
            ->andWhere('u.enabled = 1')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r, u')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.review is not null')
            ->andWhere('u.enabled = 1')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages)
            ->getQuery()
            ->setHint('knp_paginator.count', $count);
    }

    /** Update totalRating for all coasters */
    public function updateTotalRatings(): bool
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = '
            UPDATE coaster c
            LEFT JOIN (
                SELECT rc.coaster_id AS id, COUNT(rc.rating) AS nb
                FROM ridden_coaster rc
                INNER JOIN user u ON rc.user_id = u.id
                WHERE u.enabled = 1
                GROUP BY rc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.total_ratings = IFNULL(c2.nb, 0)
            ';

        try {
            $connection->executeQuery($sql);
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    /** Update averageRating for all coasters */
    public function updateAverageRatings(int $minRatings = 2): int|false
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = '
            UPDATE coaster c
            LEFT JOIN (
                SELECT rc.coaster_id AS id, ROUND(AVG(rc.rating), 3) AS average
                FROM ridden_coaster rc
                INNER JOIN user u ON rc.user_id = u.id
                WHERE u.enabled = 1
                GROUP BY rc.coaster_id
                HAVING COUNT(rc.rating) >= :minRatings
            ) c2
            ON c2.id = c.id
            SET c.averageRating = c2.average
            WHERE c2.average IS NOT NULL
            ';

        try {
            return (int) $connection->executeStatement($sql, ['minRatings' => $minRatings]);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get rating statistics for a coaster.
     *
     * @return array<int, array{rating: float, count: int}>
     */
    public function getRatingStatsForCoaster(Coaster $coaster): array
    {
        $id = $coaster->getId();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r.rating')
            ->addselect('COUNT(r.id) AS count')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :id')
            ->andWhere('u.enabled = 1')
            ->andWhere('r.rating IS NOT NULL')
            ->groupby('r.rating')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get country where a user rode the most.
     *
     * @return array{name: string, nb: int}
     */
    public function findMostRiddenCountry(User $user): array
    {
        $default = ['name' => $this->translatorInterface->trans('data.unknown', [], 'database'), 'nb' => 0];
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('co.name as name')
                ->addSelect('count(1) as nb')
                ->from(RiddenCoaster::class, 'r')
                ->join('r.coaster', 'c')
                ->join('c.park', 'p')
                ->join('p.country', 'co')
                ->where('r.user = :user')
                ->groupBy('co.id')
                ->orderBy('nb', 'desc')
                ->setParameter('user', $user)
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException|NonUniqueResultException) {
            return $default;
        }
    }

    /**
     * Count ridden coasters for a user in Top 100.
     *
     * @return array{nb_top100: int, nb_top100_operating: int}|int
     */
    public function countTop100ForUser(User $user): array|int
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select([
                    'COUNT(1) as nb_top100',
                    'SUM(CASE WHEN c.status = 1 THEN 1 ELSE 0 END) AS nb_top100_operating',
                ])
                ->from(RiddenCoaster::class, 'r')
                ->join('r.coaster', 'c')
                ->where('r.user = :user')
                ->andWhere('c.rank <= 100')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * Captain ranking scores of every ranked coaster the user has ridden.
     * Kiddies and hold-ranking coasters are excluded by virtue of having
     * no rank/score in the ranking pipeline.
     *
     * Used to feed the Captain Score calculator for a rider.
     *
     * @return list<float>
     */
    public function findRankedCoasterScoresForUser(User $user): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.score AS score')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.rank IS NOT NULL')
            ->andWhere('c.score IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn (array $row): float => (float) $row['score'], $rows));
    }

    /**
     * Ids of ranked coasters the user has ridden — used to highlight rows on the ranking.
     *
     * @return array<int, int>
     */
    public function findRankedRiddenCoasterIds(User $user): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(r.coaster) as id')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.rank IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * How many coasters of the "top 100" progress cohort the user has ridden.
     * The cohort is the best-ranked non-demolished coasters up to $cutoffRank
     * (see CoasterRepository::findTop100CohortBounds), so the result is ≤ size.
     */
    public function countRiddenInTop100Cohort(User $user, int $cutoffRank): int
    {
        if ($cutoffRank < 1) {
            return 0;
        }

        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.status', 's')
            ->where('r.user = :user')
            ->andWhere('c.rank <= :cutoff')
            ->andWhere('s.name != :defunct')
            ->setParameter('user', $user)
            ->setParameter('cutoff', $cutoffRank)
            ->setParameter('defunct', Status::CLOSED_DEFINITELY)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count of demolished (definitively closed) top-100 coasters the user got to
     * ride before they closed — celebrated on the ranking page.
     */
    public function countRiddenDefunctTop100(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.status', 's')
            ->where('r.user = :user')
            ->andWhere('c.rank <= 100')
            ->andWhere('s.name = :defunct')
            ->setParameter('user', $user)
            ->setParameter('defunct', Status::CLOSED_DEFINITELY)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return mixed|string */
    public function getMostRiddenManufacturer(User $user)
    {
        $default = ['name' => $this->translatorInterface->trans('data.unknown', [], 'database'), 'nb' => 0];
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb')
                ->addSelect('m.name as name')
                ->from(RiddenCoaster::class, 'r')
                ->join('r.coaster', 'c')
                ->join('c.manufacturer', 'm')
                ->where('r.user = :user')
                ->setParameter('user', $user)
                ->groupBy('m.id')
                ->orderBy('nb', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception) {
            return $default;
        }
    }

    public function findCoastersWithNoImage(UserInterface $user, int $max = 5): mixed
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.mainImage IS NULL')
            ->orderBy('c.totalRatings', 'desc')
            ->setFirstResult(random_int(0, $max * 2))
            ->setMaxResults($max)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user ratings for monthly ranking update.
     *
     * @return array<int, array{rating: float, coaster: int}>
     */
    public function findUserRatingsForRanking(int $userId): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->addSelect('r.rating AS rating', 'c.id AS coaster')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :id')
            ->andWhere('c.kiddie = 0')
            ->andWhere('c.holdRanking = 0')
            ->andWhere('r.rating IS NOT NULL')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get most common manufacturer among user's top list coasters (first 10-20 positions).
     *
     * @return array{name: string, nb: int}
     */
    public function getTopListManufacturer(User $user, int $maxPosition = 20): array
    {
        $default = ['name' => $this->translatorInterface->trans('data.unknown', [], 'database'), 'nb' => 0];
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb')
                ->addSelect('m.name as name')
                ->from('App\Entity\TopCoaster', 'tc')
                ->join('tc.coaster', 'c')
                ->join('c.manufacturer', 'm')
                ->join('tc.top', 't')
                ->where('t.user = :user')
                ->andWhere('t.main = 1')
                ->andWhere('tc.position <= :maxPosition')
                ->setParameter('user', $user)
                ->setParameter('maxPosition', $maxPosition)
                ->groupBy('m.id')
                ->orderBy('nb', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception) {
            return $default;
        }
    }

    /**
     * Get featured reviews for a coaster (high-rated with text content).
     * Prioritizes reviews in the user's locale, sorted by score and upvotes.
     *
     * @param Coaster $coaster The coaster to get reviews for
     * @param string  $locale  The preferred language
     * @param int     $limit   Maximum number of reviews to retrieve
     *
     * @return array<int, RiddenCoaster>
     */
    public function getFeaturedReviews(Coaster $coaster, string $locale = 'en', int $limit = 3): array
    {
        // First query: get IDs without collection joins to avoid row multiplication
        /** @var list<int> $ids */
        $ids = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r.id')
            ->addSelect('CASE WHEN r.language = :locale THEN 0 ELSE 1 END AS HIDDEN languagePriority')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :coasterId')
            ->andWhere('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->andWhere('u.enabled = 1')
            ->andWhere('r.rating >= 3')
            ->orderBy('languagePriority', 'ASC')
            ->addOrderBy('r.score', 'DESC')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('locale', $locale)
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        if ([] === $ids) {
            return [];
        }

        // Second query: fetch full entities with eager-loaded collections
        $reviews = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'u', 'p', 'c')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->leftJoin('r.pros', 'p')
            ->leftJoin('r.cons', 'c')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Preserve original ordering
        $idOrder = array_flip($ids);
        usort($reviews, static fn (RiddenCoaster $a, RiddenCoaster $b): int => $idOrder[$a->getId()] <=> $idOrder[$b->getId()]);

        return $reviews;
    }

    /**
     * Get a sample of reviews in a specific language for terminology analysis.
     *
     * @param string $language The target language code
     * @param int    $limit    Maximum number of reviews to retrieve
     *
     * @return array<int, RiddenCoaster> Array of RiddenCoaster entities with review text
     */
    public function findReviewSampleByLanguage(string $language, int $limit): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.language = :language')
            ->andWhere('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'DESC')
            ->setParameter('language', $language)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get reviews with text content for a specific coaster in a specific language.
     * Returns RiddenCoaster entities with review text and rating values.
     *
     * @param Coaster  $coaster  The coaster to get reviews for
     * @param string   $language The target language code
     * @param int|null $limit    Maximum number of reviews to retrieve
     *
     * @return array<int, RiddenCoaster> Array of RiddenCoaster entities with review text and ratings
     */
    public function getCoasterReviewsWithTextByLanguage(Coaster $coaster, string $language, ?int $limit = null): array
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :coasterId')
            ->andWhere('r.language = :language')
            ->andWhere('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'desc')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('language', $language);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all RiddenCoasters for a user in a specific park.
     *
     * @return array<int, RiddenCoaster>
     */
    public function findByUserAndPark(User $user, Park $park): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'c')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.park = :park')
            ->setParameter('user', $user)
            ->setParameter('park', $park)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find RiddenCoasters for a user's journey, sorted by firstRiddenAt DESC.
     *
     * @return array<int, RiddenCoaster>
     */
    public function findByUserForJourney(User $user, ?int $year = null): array
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'c', 'p')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.firstRiddenAt', 'DESC');

        if (null !== $year) {
            $qb->andWhere('r.firstRiddenAt >= :yearStart')
                ->andWhere('r.firstRiddenAt < :yearEnd')
                ->setParameter('yearStart', new \DateTimeImmutable("$year-01-01"))
                ->setParameter('yearEnd', new \DateTimeImmutable(($year + 1).'-01-01'));
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array{total: int, parks: int, countries: int, since: int|null} */
    public function getJourneyStats(User $user): array
    {
        $result = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(
                'COUNT(r.id) as total',
                'COUNT(DISTINCT p.id) as parks',
                'COUNT(DISTINCT country.id) as countries',
                'MIN(r.firstRiddenAt) as since',
            )
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->leftJoin('p.country', 'country')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        $since = $result['since'];

        return [
            'total' => (int) $result['total'],
            'parks' => (int) $result['parks'],
            'countries' => (int) $result['countries'],
            // DQL returns MIN(DateTime) as a string "YYYY-MM-DD HH:MM:SS"
            'since' => $since ? (int) substr((string) $since, 0, 4) : null,
        ];
    }

    /** @return list<int> Years with at least one dated ride, sorted DESC. */
    public function findAvailableRideYears(User $user): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT YEAR(first_ridden_at) AS y FROM ridden_coaster WHERE user_id = :userId AND first_ridden_at IS NOT NULL ORDER BY y DESC',
            ['userId' => $user->getId()],
        );

        return array_map(static fn (array $row): int => (int) $row['y'], $rows);
    }

    /**
     * Return a map of coaster ID → milestone number (50, 100, 150 …) for the user's dated rides.
     *
     * @return array<int, int>
     */
    public function findMilestoneCoasterIds(User $user, int $interval = 50): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(r.coaster) as coasterId', 'r.firstRiddenAt', 'r.id')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.firstRiddenAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('r.firstRiddenAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_SCALAR);

        $milestones = [];
        foreach ($rows as $i => $row) {
            $num = $i + 1;
            if (0 === $num % $interval) {
                $milestones[(int) $row['coasterId']] = $num;
            }
        }

        return $milestones;
    }

    /** Get a QueryBuilder for RiddenCoasters without a rating (ridden only). */
    public function findRiddenOnlyByUser(User $user): QueryBuilder
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'c', 'p')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->andWhere('r.rating IS NULL')
            ->setParameter('user', $user);
    }

    /** Get a QueryBuilder for RiddenCoasters with a rating. */
    public function findRatedByUser(User $user): QueryBuilder
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'c', 'p')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->andWhere('r.rating IS NOT NULL')
            ->setParameter('user', $user);
    }

    /** Count coasters a user rode for the first time during the current calendar year. */
    public function countNewCoastersThisYear(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.firstRiddenAt >= :yearStart')
            ->setParameter('user', $user)
            ->setParameter('yearStart', new \DateTimeImmutable(date('Y').'-01-01'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total rides during the current calendar year — counts each ride from re-rides
     * by checking lastRiddenAt for re-rides happening this year, and firstRiddenAt
     * for first-time rides. Falls back to firstRiddenAt-based count for simplicity.
     */
    public function countTotalRidesThisYear(User $user): int
    {
        // First-time rides this year
        $firstRides = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.firstRiddenAt >= :yearStart')
            ->setParameter('user', $user)
            ->setParameter('yearStart', new \DateTimeImmutable(date('Y').'-01-01'))
            ->getQuery()
            ->getSingleScalarResult();

        // Re-rides this year (lastRiddenAt this year, but firstRiddenAt earlier;
        // we don't know how many re-rides happened this year exactly, so we count
        // each such ridden coaster as +1 ride for the year).
        $reRides = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.lastRiddenAt >= :yearStart')
            ->andWhere('r.rideCount > 1')
            ->andWhere('(r.firstRiddenAt IS NULL OR r.firstRiddenAt < :yearStart)')
            ->setParameter('user', $user)
            ->setParameter('yearStart', new \DateTimeImmutable(date('Y').'-01-01'))
            ->getQuery()
            ->getSingleScalarResult();

        return $firstRides + $reRides;
    }

    /**
     * Most recently touched rides (rated or not) for the profile activity feed.
     *
     * @return array<int, RiddenCoaster>
     */
    public function findRecentActivity(User $user, int $limit = 6): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'c', 'p')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->orderBy('r.updatedAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Count ridden coasters with a rating for a user. */
    public function countRatedForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.rating IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The "biggest" coaster a user has ridden for a numeric metric — used for
     * personal-record superlatives on the profile (tallest, fastest, longest, most inversions).
     */
    public function findUserSuperlativeByMetric(User $user, string $metric): ?Coaster
    {
        if (!\in_array($metric, ['height', 'speed', 'length', 'inversionsNumber'], true)) {
            return null;
        }

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->innerJoin(RiddenCoaster::class, 'r', 'WITH', 'r.coaster = c')
            ->where('r.user = :user')
            ->andWhere(\sprintf('c.%s IS NOT NULL', $metric))
            ->andWhere(\sprintf('c.%s > 0', $metric))
            ->orderBy(\sprintf('c.%s', $metric), 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Oldest (ASC) or newest (DESC) coaster a user has ridden, by opening date. */
    public function findUserCoasterByOpeningDate(User $user, string $direction = 'ASC'): ?Coaster
    {
        $direction = 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC';

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->innerJoin(RiddenCoaster::class, 'r', 'WITH', 'r.coaster = c')
            ->where('r.user = :user')
            ->andWhere('c.openingDate IS NOT NULL')
            ->orderBy('c.openingDate', $direction)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Total rides for a user, counting re-rides (sum of rideCount). */
    public function getTotalRideCount(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COALESCE(SUM(r.rideCount), 0)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Average rating a user gives (null if they have rated nothing). */
    public function getUserAverageRating(User $user): ?float
    {
        $avg = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('AVG(r.rating)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.rating IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $avg ? round((float) $avg, 2) : null;
    }

    /**
     * Distribution of a user's own ratings, grouped by star value.
     *
     * @return array<int, array{rating: float, count: int}>
     */
    public function getUserRatingDistribution(User $user): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r.rating')
            ->addSelect('COUNT(r.id) AS count')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->andWhere('r.rating IS NOT NULL')
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Most-ridden vocabulary value (materialType, seatingType, or model) for a user.
     *
     * @return array{name: string, nb: int}|null
     */
    public function getMostRiddenByVocabulary(User $user, string $assoc): ?array
    {
        if (!\in_array($assoc, ['materialType', 'seatingType', 'model'], true)) {
            return null;
        }

        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb')
                ->addSelect('v.name as name')
                ->from(RiddenCoaster::class, 'r')
                ->join('r.coaster', 'c')
                ->join(\sprintf('c.%s', $assoc), 'v')
                ->where('r.user = :user')
                ->setParameter('user', $user)
                ->groupBy('v.id')
                ->orderBy('nb', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception) {
            return null;
        }
    }
}
