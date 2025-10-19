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

/**
 * RiddenCoasterRepository.
 */
class RiddenCoasterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiddenCoaster::class);
    }

    /** Count all ratings. */
    public function countAll()
    {
        try {
            return $this->getEntityManager()
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
    public function countReviews()
    {
        return $this->getEntityManager()
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
    public function countNew(\DateTime $date)
    {
        return $this->getEntityManager()
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
    public function countForUser(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(RiddenCoaster::class, 'r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForCoaster(Coaster $coaster)
    {
        try {
            return $this->getEntityManager()
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

    /** Get ratings for a specific coaster ordered by language preference, score and date. */
    public function getCoasterReviews(Coaster $coaster, string $locale = 'en', bool $displayReviewsInAllLanguages = true)
    {
        // add joins to avoid multiple subqueries
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'p', 'c', 'u')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->leftJoin('r.pros', 'p')
            ->leftjoin('r.cons', 'c')
            ->where('r.coaster = :coasterId')
            ->andWhere('u.enabled = 1')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.score', 'desc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages)
            ->getQuery()
            ->getResult();
    }

    /** Get latest text reviews ordered by language. */
    public function getLatestReviews(string $locale = 'en', int $limit = 3, bool $displayReviewsInAllLanguages = false)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->addSelect(
                'CASE WHEN (r.language = :locale OR :displayReviewsInAllLanguages = 1) AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority'
            )
            ->addSelect('u')
            ->from(RiddenCoaster::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.review is not null')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setMaxResults($limit)
            ->setParameter('locale', $locale)
            ->setParameter('displayReviewsInAllLanguages', $displayReviewsInAllLanguages)
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

    /** Get country where a user rode the most. */
    public function findMostRiddenCountry(User $user)
    {
        $default = ['name' => '-', 'nb' => 0];
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
            return ['nb' => 0, 'name' => 'Unknown'];
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

    /** Get most common manufacturer among user's top list coasters (first 10-20 positions) */
    public function getTopListManufacturer(User $user, int $maxPosition = 20)
    {
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
            return null;
        }
    }
}
