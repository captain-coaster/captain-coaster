<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
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

    /**
     * Get latest text reviews ordered by language.
     *
     * @param array<string> $preferredReviewLanguages
     *
     * @return array<int, RiddenCoaster>
     */
    public function getLatestReviews(array $preferredReviewLanguages = ['en'], int $limit = 3): array
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->addSelect(
                'CASE WHEN r.language IN (:preferredReviewLanguages) THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->addSelect('u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.hasReview = 1')
            ->andWhere('u.enabled = 1')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setMaxResults($limit)
            ->setParameter('preferredReviewLanguages', $preferredReviewLanguages)
            ->getQuery();

        $query->enableResultCache(300);

        return $query->getResult();
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
            ->orderBy('r.updatedAt', 'desc')
            ->setMaxResults($limit)
            ->getQuery();

        $query->enableResultCache(300);

        return $query->getResult();
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
     * @param iterable<mixed> $reviews
     */
    public function preloadTags(iterable $reviews): void
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
            $this
                ->createQueryBuilder('r')
                ->select('r', 't')
                ->leftJoin('r.'.$association, 't')
                ->where('r.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
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
     * @param array<string> $preferredReviewLanguages
     *
     * @return Query<mixed, mixed>
     */
    public function findAllReviews(array $preferredReviewLanguages): Query
    {
        $count = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.hasReview = 1')
            ->andWhere('r.language IN (:preferredReviewLanguages)')
            ->andWhere('u.enabled = 1')
            ->setParameter('preferredReviewLanguages', $preferredReviewLanguages)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r, u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.hasReview = 1')
            ->andWhere('r.language IN (:preferredReviewLanguages)')
            ->andWhere('u.enabled = 1')
            ->orderBy('r.updatedAt', 'desc')
            ->setParameter('preferredReviewLanguages', $preferredReviewLanguages)
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
     * @return array<int, array{value: float, count: int}>
     */
    public function getRatingStatsForCoaster(Coaster $coaster): array
    {
        $id = $coaster->getId();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r.value')
            ->addselect('COUNT(r.id) AS count')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.coaster = :id')
            ->andWhere('u.enabled = 1')
            ->groupby('r.value')
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
     * nb_top100 counts ridden coasters within the overall Top 100 (closed/destroyed
     * coasters included, since they still hold their all-time rank). nb_top100_operating
     * counts ridden coasters within the top 100 *still-operating* coasters — a separate
     * ranking with closed ones excluded entirely, not just the operating subset of the
     * overall Top 100. Otherwise closed coasters occupying overall-Top-100 slots would
     * make 100/100 operating permanently unreachable.
     *
     * @return array{nb_top100: int, nb_top100_operating: int}|int
     */
    public function countTop100ForUser(User $user): array|int
    {
        $operatingTop100Ids = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id')
            ->from(Coaster::class, 'c')
            ->join('c.status', 's')
            ->where('s.name = :operating')
            ->andWhere('c.rank IS NOT NULL')
            ->orderBy('c.rank', 'ASC')
            ->setMaxResults(100)
            ->setParameter('operating', Status::OPERATING)
            ->getQuery()
            ->getSingleColumnResult();

        // Guard against an empty IN(), which Doctrine can't compile.
        if ([] === $operatingTop100Ids) {
            $operatingTop100Ids = [0];
        }

        try {
            return $this->getEntityManager()
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
                ->getQuery()
                ->getSingleResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
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
            ->addSelect('r.value AS rating', 'c.id AS coaster')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :id')
            ->andWhere('c.kiddie = 0')
            ->andWhere('c.holdRanking = 0')
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
