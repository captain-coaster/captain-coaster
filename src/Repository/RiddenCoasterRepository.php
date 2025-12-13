<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * RiddenCoasterRepository.
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

        return $query->getSingleScalarResult();
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

    /** Get ratings for a specific coaster. */
    public function getCoasterReviews(
        Coaster $coaster,
        string $locale = 'en',
        bool $displayReviewsInAllLanguages = true,
        $filters = []
    ) {
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
            ->orderBy('languagePriority', 'asc')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages);

        $this->applyFilters($query, $filters);

        return $query->getQuery()
            ->getResult();
    }

    private function applyFilters($query, $filters): void
    {
        // Sorting
        $this->sort($query, $filters);
    }

    private function sort($query, $filters): void
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

    private function defaultSort($query): void
    {
        $query
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
            return $this->getEntityManager()
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
            ->where('r.review is not null')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r, u')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->from(RiddenCoaster::class, 'r')
            ->join('r.user', 'u')
            ->where('r.review is not null')
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
            JOIN (
                SELECT rc.coaster_id AS id, COUNT(rating) AS nb
                FROM ridden_coaster rc
                GROUP BY rc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.total_ratings = c2.nb
            ';

        try {
            $connection->executeQuery($sql);
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    /** Update averageRating for all coasters */
    public function updateAverageRatings(int $minRatings): bool|int
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = '
            UPDATE coaster c
            JOIN (
                SELECT rc.coaster_id AS id, FORMAT(AVG(rating), 3) AS average
                FROM ridden_coaster rc
                GROUP BY rc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.averageRating = c2.average
            WHERE c.total_ratings >= :minRatings
            ';

        try {
            return $connection->executeStatement($sql, ['minRatings' => $minRatings]);
        } catch (\Exception) {
            return false;
        }
    }

    /** Get rating statistics for a coaster */
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

    /** Get country where a user rode the most. */
    public function findMostRiddenCountry(User $user)
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

    /** Count ridden coasters for a user in Top 100. */
    public function countTop100ForUser(User $user)
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

    /** Get user ratings for monthly ranking update */
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

    /** Get most common manufacturer among user's top list coasters (first 10-20 positions) */
    public function getTopListManufacturer(User $user, int $maxPosition = 20)
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
     * @return array Array of RiddenCoaster entities with review text
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
     * @return array Array of RiddenCoaster entities with review text and ratings
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
}
