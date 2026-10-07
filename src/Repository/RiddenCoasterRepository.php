<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\ReviewUpvote;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RiddenCoaster>
 */
class RiddenCoasterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiddenCoaster::class);
    }

    /** Count all ratings. */
    public function countAll(): int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_rating')
                ->from(RiddenCoaster::class, 'r')
                ->getQuery()
                ->getSingleScalarResult();
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
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(r.review) as nb_review')
            ->from(RiddenCoaster::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all new ratings since date passed in parameter.
     *
     * @throws NonUniqueResultException
     */
    public function countNew(\DateTime $date): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.createdAt > :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
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

    /** Ratings of a user that feed the ranking (same scope as iterateRatingsForRanking()). */
    public function countRankedForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.kiddie = 0')
            ->andWhere('c.holdRanking = 0')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * A member's Home figures: coasters ridden, those with a ride date in $year, reviews written.
     *
     * @return array{ridden: int, riddenInYear: int, reviews: int}
     */
    public function countFiguresForUser(User $user, int $year): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS ridden')
            ->addSelect('SUM(CASE WHEN r.riddenAt BETWEEN :start AND :end THEN 1 ELSE 0 END) AS riddenInYear')
            ->addSelect('SUM(CASE WHEN r.hasReview = 1 THEN 1 ELSE 0 END) AS reviews')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->setParameter('start', \sprintf('%d-01-01', $year))
            ->setParameter('end', \sprintf('%d-12-31', $year))
            ->getQuery()
            ->getSingleResult();

        return ['ridden' => (int) $row['ridden'], 'riddenInYear' => (int) $row['riddenInYear'], 'reviews' => (int) $row['reviews']];
    }

    /**
     * Authors whose reviews collected at least $minVotes upvotes in total: a single review rarely gets enough
     * votes to be ranked on them, an author does.
     *
     * @return array<int>
     */
    public function findReputedAuthorIds(int $minVotes): array
    {
        return array_map('intval', $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(r.user)')
            ->from(ReviewUpvote::class, 'v')
            ->join('v.review', 'r')
            ->groupBy('r.user')
            ->having('COUNT(v.id) >= :minVotes')
            ->setParameter('minVotes', $minVotes)
            ->getQuery()
            ->enableResultCache(21600)
            ->getSingleColumnResult());
    }

    /**
     * Reviews of at least $minLength characters written by $authorIds since $since, latest first.
     * $since is part of the cache key: pass a date, not a moving timestamp.
     *
     * @param array<int> $authorIds
     *
     * @return list<RiddenCoaster>
     */
    public function findRecentReviewsByAuthors(array $authorIds, \DateTimeInterface $since, int $minLength, int $limit): array
    {
        if ([] === $authorIds) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('u', 'c', 'p', 'mi')
            ->innerJoin('r.user', 'u')
            ->innerJoin('r.coaster', 'c')
            ->innerJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'mi')
            ->where('r.hasReview = 1')
            ->andWhere('r.updatedAt >= :since')
            ->andWhere('r.user IN (:authors)')
            ->andWhere('LENGTH(r.review) >= :minLength')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'DESC')
            ->setParameter('since', $since)
            ->setParameter('authors', $authorIds)
            ->setParameter('minLength', $minLength)
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(300)
            ->getResult();
    }

    /** A user's latest rating without a review, on a coaster first rated since $since. */
    public function findLatestWithoutReview(User $user, \DateTimeInterface $since): ?RiddenCoaster
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c', 'mi')
            ->innerJoin('r.coaster', 'c')
            ->leftJoin('c.mainImage', 'mi')
            ->where('r.user = :user')
            ->andWhere('r.review IS NULL')
            ->andWhere('r.createdAt >= :since')
            ->orderBy('r.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The operating coaster a user rated last among those with fewer than $maxPhotos photos.
     *
     * @return array{coaster: Coaster, photos: int}|null
     */
    public function findLatestWithFewPhotos(User $user, int $maxPhotos): ?array
    {
        // Used twice in the query, so each use needs its own alias.
        $count = static fn (string $alias): string => \sprintf('(SELECT COUNT(%1$s.id) FROM %2$s %1$s WHERE %1$s.coaster = c AND %1$s.enabled = 1)', $alias, Image::class);

        $row = $this->createQueryBuilder('r')
            ->addSelect('c', 'mi', $count('shown').' AS photos')
            ->innerJoin('r.coaster', 'c')
            ->innerJoin('c.status', 's')
            ->leftJoin('c.mainImage', 'mi')
            ->where('r.user = :user')
            ->andWhere('s.code = :operating')
            ->andWhere($count('counted').' < :maxPhotos')
            ->orderBy('r.updatedAt', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('operating', Status::OPERATING)
            ->setParameter('maxPhotos', $maxPhotos)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null === $row ? null : ['coaster' => $row[0]->getCoaster(), 'photos' => (int) $row['photos']];
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
     * @param array<string>        $preferredReviewLanguages
     * @param array<string, mixed> $filters
     */
    public function getCoasterReviews(
        Coaster $coaster,
        array $preferredReviewLanguages = ['en'],
        array $filters = []
    ): QueryBuilder {
        // add joins to avoid multiple subqueries
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'p', 'c', 'u', 'up', 'co')
            ->addSelect(
                'CASE WHEN r.language IN (:preferredReviewLanguages) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
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
            ->setParameter('preferredReviewLanguages', $preferredReviewLanguages);

        $this->applyFilters($query, $filters);

        return $query;
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
        $sortingOptions = ['value', 'updatedAt'];

        if (\array_key_exists('sort', $filters) && '' !== $filters['sort'] && str_contains($filters['sort'], '|')) {
            $sort = explode('|', $filters['sort']);

            if (!\in_array($sort[0], $sortingOptions) || !\in_array($sort[1], ['ASC', 'DESC', 'asc', 'desc'])) {
                $this->defaultSort($query);
            } else {
                $query->addOrderBy('r.'.$sort[0], $sort[1]);
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
     * Get a random-ish sample of reviews with text content, for moderation
     * calibration. Uses a random offset rather than ORDER BY RAND() to avoid
     * a full-table sort — good enough for calibration sampling, not intended
     * for anything requiring true uniform randomness.
     *
     * @return array<int, RiddenCoaster>
     */
    public function findRandomReviewsWithText(int $sample): array
    {
        $total = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(r.id)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = $total > $sample ? random_int(0, $total - $sample) : 0;

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->orderBy('r.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($sample)
            ->getQuery()
            ->getResult();
    }

    /** @return QueryBuilder */
    public function getUserRatings(User $user)
    {
        return $this->getEntityManager()
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
    }

    /**
     * Initialises the pros/cons collections of the given reviews, two queries total.
     *
     * Includes/_review_item.html.twig reads review.pros and review.cons, both lazy
     * ManyToMany. Without this, rendering N reviews fires 2N collection-loading
     * queries from inside Twig — invisible in the template's own timing, and the
     * shape production's FPM slowlog caught at over a second.
     *
     * Not needed for getCoasterReviews(), which already fetch-joins both.
     *
     * $resultCacheTtl should only be set by callers whose id set is shared
     * sitewide and low-cardinality (e.g. "latest N reviews"). Paginated
     * per-user/per-page listings must leave it null: their id set varies
     * per page and per user, and caching those would fill the result cache
     * with entries almost never reused before they expire.
     *
     * @param iterable<mixed> $reviews
     */
    public function preloadTags(iterable $reviews, ?int $resultCacheTtl = null): void
    {
        $ids = [];
        foreach ($reviews as $review) {
            if ($review instanceof RiddenCoaster) {
                $ids[] = $review->getId();
            }
        }

        if ([] === $ids) {
            return;
        }

        // Two separate queries on purpose: joining both ManyToMany at once
        // multiplies rows into a cartesian product.
        foreach (['pros', 'cons'] as $association) {
            $query = $this
                ->createQueryBuilder('r')
                ->select('r', 't')
                ->leftJoin('r.'.$association, 't')
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery();

            if (null !== $resultCacheTtl) {
                $query->enableResultCache($resultCacheTtl);
            }

            $query->getResult();
        }
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
     * Get reviews with text in one of the given languages. Unlike the other
     * review listings, this feed excludes non-matching reviews entirely
     * (rather than keeping the row and hiding its text) and applies no
     * language-based sort priority -- it's a dedicated reading feed, so a
     * rating-only row with its text hidden would just be dead weight.
     *
     * Fetch-joins coaster/park: Review/list.html.twig renders both per
     * review (name/slug for its links), and they're plain LAZY ManyToOne --
     * unlike Coaster's own EAGER associations, nothing loads them
     * automatically, so up to 20 extra queries per page (10 reviews x
     * coaster + park) without this.
     *
     * No OFFSET, ever: the listing grows $limit for each "load more" (see
     * ReviewController::listAction()), so a deep load costs one bounded
     * index scan instead of the COUNT + skip-N-rows cost a page-number
     * scheme pays at depth. Same mechanism as
     * NotificationRecipientRepository::findPageForUser().
     *
     * @param array<string> $preferredReviewLanguages
     *
     * @return list<RiddenCoaster>
     */
    public function findAllReviews(array $preferredReviewLanguages, int $limit): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r, u, c, p')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->innerJoin('r.coaster', 'c')
            ->innerJoin('c.park', 'p')
            ->where('r.hasReview = 1')
            ->andWhere('r.language IN (:preferredReviewLanguages)')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'desc')
            ->addOrderBy('r.id', 'desc')
            ->setParameter('preferredReviewLanguages', $preferredReviewLanguages)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
                INNER JOIN users u ON rc.user_id = u.id
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
                INNER JOIN users u ON rc.user_id = u.id
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
     * Cached under a per-coaster id, cleared by RiddenCoasterListener on every rating change so that a rider sees
     * their own rating right away. The TTL is a backstop (e.g. a disabled account's ratings leaving the counts).
     *
     * @return array<int, array{value: float, count: int}>
     */
    public function getRatingStatsForCoaster(Coaster $coaster): array
    {
        $id = $coaster->getId();

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r.value')
            ->addselect('COUNT(r.id) AS count')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :id')
            ->andWhere('u.enabled = 1')
            ->groupby('r.value')
            ->setParameter('id', $id)
            ->getQuery();

        $query->enableResultCache(3600, $this->ratingStatsCacheId($coaster));

        return $query->getResult();
    }

    public function clearRatingStatsCache(Coaster $coaster): void
    {
        $this->getEntityManager()->getConfiguration()->getResultCache()?->deleteItem($this->ratingStatsCacheId($coaster));
    }

    private function ratingStatsCacheId(Coaster $coaster): string
    {
        return \sprintf('coaster_rating_stats_%d', $coaster->getId());
    }

    /**
     * Count ridden coasters for a user in Top 100.
     *
     * nb_top100 counts ridden coasters within the overall Top 100 (closed/destroyed
     * coasters included, since they still hold their all-time rank). nb_top100_operating
     * counts ridden coasters within the top 100 *still-operating* coasters — a separate
     * ranking with closed ones excluded entirely, not just the operating subset of the
     * overall Top 100. Otherwise closed coasters occupying overall-Top-100 slots would
     * make 100/100 operating permanently unreachable.
     *
     * @return array{nb_top100: int, nb_top100_operating: int}
     */
    public function countTop100ForUser(User $user): array
    {
        $idsQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id')
            ->from(Coaster::class, 'c')
            ->join('c.status', 's')
            ->where('s.code = :operating')
            ->andWhere('c.rank IS NOT NULL')
            ->orderBy('c.rank', 'ASC')
            ->setMaxResults(100)
            ->setParameter('operating', Status::OPERATING)
            ->getQuery();

        // Identical for every user -- only the monthly ranking publication
        // changes it, and clears it -- so this is shared across every profile
        // page view rather than recomputed per visit. The per-user aggregate below
        // isn't cached: it's a single indexed, user-scoped query (a few ms
        // even for the platform's most active rider), so caching it would
        // only trade a negligible query for real staleness risk.
        $idsQuery->enableResultCache(RankingRepository::RANK_CACHE_TTL);
        $operatingTop100Ids = $idsQuery->getSingleColumnResult();

        // Guard against an empty IN(), which Doctrine can't compile.
        if ([] === $operatingTop100Ids) {
            $operatingTop100Ids = [0];
        }

        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select([
                'SUM(CASE WHEN c.rank <= 100 THEN 1 ELSE 0 END) as nb_top100',
                'SUM(CASE WHEN c.id IN (:operatingTop100Ids) THEN 1 ELSE 0 END) AS nb_top100_operating',
            ])
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.rank <= 100 OR c.id IN (:operatingTop100Ids)')
            ->setParameter('user', $user)
            ->setParameter('operatingTop100Ids', $operatingTop100Ids)
            ->getQuery();

        // One row even when nothing matches (aggregate, no GROUP BY), but SUM() is then NULL.
        $result = $query->getSingleResult();

        return [
            'nb_top100' => (int) $result['nb_top100'],
            'nb_top100_operating' => (int) $result['nb_top100_operating'],
        ];
    }

    /**
     * Ids among $coasterIds the user has ridden.
     *
     * @param int[] $coasterIds
     *
     * @return int[]
     */
    public function findRiddenCoasterIds(User $user, array $coasterIds): array
    {
        if ([] === $coasterIds) {
            return [];
        }

        return array_map('intval', $this->createQueryBuilder('r')
            ->select('IDENTITY(r.coaster)')
            ->where('r.user = :user')
            ->andWhere('r.coaster IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $coasterIds)
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * The coaster the user rode with the highest $field (height, speed, length or inversionsNumber); ties go to the
     * best ranked, then the lowest id. Park and main image are loaded with it.
     */
    public function findRiddenWithMaximum(User $user, string $field): ?Coaster
    {
        if (!\in_array($field, ['height', 'speed', 'length', 'inversionsNumber'], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown coaster field "%s".', $field));
        }

        return $this->riddenCoasters($user)
            ->andWhere(\sprintf('c.%s IS NOT NULL', $field))
            ->orderBy(\sprintf('c.%s', $field), 'DESC')
            ->addOrderBy('rankMissing', 'ASC')
            ->addOrderBy('c.rank', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** The coaster the user rode that opened first, from $since on (ties: best ranked, then lowest id). */
    public function findOldestRidden(User $user, \DateTimeInterface $since): ?Coaster
    {
        return $this->riddenCoasters($user)
            ->andWhere('c.openingDate >= :since')
            ->setParameter('since', $since)
            ->orderBy('c.openingDate', 'ASC')
            ->addOrderBy('rankMissing', 'ASC')
            ->addOrderBy('c.rank', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Coasters ridden by the user, one result at most, park and main image fetched. */
    private function riddenCoasters(User $user): QueryBuilder
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'p', 'mi')
            ->addSelect('CASE WHEN c.rank IS NULL THEN 1 ELSE 0 END AS HIDDEN rankMissing')
            ->from(Coaster::class, 'c')
            ->innerJoin(RiddenCoaster::class, 'r', 'WITH', 'r.coaster = c AND r.user = :user')
            ->innerJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'mi')
            ->setParameter('user', $user)
            ->setMaxResults(1);
    }

    /**
     * Number of ratings of the user per rating value.
     *
     * @return list<array{value: float, count: int}>
     */
    public function countRatingsByValue(User $user): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.value AS value', 'COUNT(r.id) AS nb')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->groupBy('r.value')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row): array => ['value' => (float) $row['value'], 'count' => (int) $row['nb']], $rows));
    }

    /**
     * The user's review with the most upvotes (ties: the latest) among those of $minLength characters or more, from
     * one upvote. Coaster, park and main image fetched.
     */
    public function findMostUpvotedReview(User $user, int $minLength): ?RiddenCoaster
    {
        return $this->createQueryBuilder('r')
            ->addSelect('c', 'p', 'mi')
            ->innerJoin('r.coaster', 'c')
            ->innerJoin('c.park', 'p')
            ->leftJoin('c.mainImage', 'mi')
            ->where('r.user = :user')
            ->andWhere('r.hasReview = 1')
            ->andWhere('r.upvoteCounter > 0')
            ->andWhere('LENGTH(r.review) >= :minLength')
            ->setParameter('user', $user)
            ->setParameter('minLength', $minLength)
            ->orderBy('r.upvoteCounter', 'DESC')
            ->addOrderBy('r.updatedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Total upvotes received by the user's reviews. */
    public function sumReviewUpvotesForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.upvoteCounter), 0)')
            ->where('r.user = :user')
            ->andWhere('r.hasReview = 1')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Number of coasters ridden per ride date, for the dated rides.
     *
     * @return list<array{date: \DateTimeInterface, count: int}>
     */
    public function countRidesByDate(User $user): array
    {
        /** @var list<array{date: \DateTimeInterface, nb: numeric-string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.riddenAt AS date', 'COUNT(r.id) AS nb')
            ->where('r.user = :user')
            ->andWhere('r.riddenAt IS NOT NULL')
            ->setParameter('user', $user)
            ->groupBy('r.riddenAt')
            ->getQuery()
            ->getResult();

        return array_values(array_map(static fn (array $row): array => ['date' => $row['date'], 'count' => (int) $row['nb']], $rows));
    }

    /**
     * Ratings of enabled users that feed the ranking, sorted by user. Plain SQL, streamed: close to a million rows.
     *
     * @return \Traversable<int, list<mixed>> user id, coaster id, rating
     */
    public function iterateRatingsForRanking(): \Traversable
    {
        return $this->getEntityManager()->getConnection()->iterateNumeric(
            'SELECT r.user_id, r.coaster_id, r.rating
            FROM ridden_coaster r
            JOIN users u ON u.id = r.user_id
            JOIN coaster c ON c.id = r.coaster_id
            WHERE u.enabled = 1 AND c.kiddie = 0 AND c.hold_ranking = 0 AND r.rating IS NOT NULL
            ORDER BY r.user_id'
        );
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
     * Reviews pending moderation analysis (moderatedAt is null), optionally
     * restricted to those created or edited since a given time. Passing
     * $since = null processes the full backlog (explicit --all mode).
     *
     * @return array<int, RiddenCoaster>
     */
    public function findPendingAnalysis(?\DateTimeInterface $since, int $limit): array
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->andWhere('r.moderatedAt IS NULL')
            ->orderBy('r.id', 'ASC')
            ->setMaxResults($limit);

        if (null !== $since) {
            $qb->andWhere('(r.createdAt > :since OR r.updatedAt > :since)')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
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
     * Get reviews with text content for a coaster in languages OTHER than the given one.
     * Used to backfill the analysis set for a thin-language summary with a more
     * representative sample, while still writing the output in the target language.
     *
     * @return array<int, RiddenCoaster>
     */
    public function getCoasterReviewsWithTextExcludingLanguage(Coaster $coaster, string $excludeLanguage, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :coasterId')
            ->andWhere('r.language != :language')
            ->andWhere('r.review IS NOT NULL')
            ->andWhere('TRIM(r.review) != \'\'')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'desc')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('language', $excludeLanguage)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Count reviews with text content for a specific coaster in a specific language. */
    public function countCoasterReviewsWithTextByLanguage(Coaster $coaster, string $language): int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(r.id)')
                ->from(RiddenCoaster::class, 'r')
                ->innerJoin('r.user', 'u')
                ->where('r.coaster = :coasterId')
                ->andWhere('r.language = :language')
                ->andWhere('r.review IS NOT NULL')
                ->andWhere('TRIM(r.review) != \'\'')
                ->andWhere('u.enabled = 1')
                ->setParameter('coasterId', $coaster->getId())
                ->setParameter('language', $language)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Count reviews with text content for a coaster across all languages. Used to check
     * whether a coaster has enough content overall to generate a summary once backfill
     * from other languages is taken into account (see CoasterSummaryService::MIN_REVIEWS_REQUIRED).
     */
    public function countAllReviewsWithText(Coaster $coaster): int
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
}
