<?php

namespace BddBundle\Repository;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\User;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * RiddenCoasterRepository
 */
class RiddenCoasterRepository extends EntityRepository
{
    /**
     * Count all ratings
     *
     * @return mixed
     */
    public function countAll()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_rating')
                ->from('BddBundle:RiddenCoaster', 'r')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException $e) {
            return 0;
        }
    }

    /**
     * Count all ratings with text review
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countReviews()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(r.review) as nb_review')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all new ratings since date passed in parameter
     * @param \DateTime $date
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countNew(\DateTime $date)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->where('r.createdAt > :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count all ratings for a specific user passed in parameter
     * @param User $user
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countForUser(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param Coaster $coaster
     * @return mixed
     */
    public function countForCoaster(Coaster $coaster)
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from('BddBundle:RiddenCoaster', 'r')
                ->where('r.coaster = :coaster')
                ->setParameter('coaster', $coaster)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * Get ratings for a specific coaster ordered by language preference, text review and date
     * @param Coaster $coaster
     * @param string $locale
     * @return mixed
     */
    public function getReviews(Coaster $coaster, string $locale = 'en')
    {
        // add joins to avoid multiple subqueries
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'p', 'c', 'u')
            ->addSelect(
                "CASE WHEN r.language = :locale AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority"
            )
            ->from('BddBundle:RiddenCoaster', 'r')
            ->innerJoin('r.user', 'u')
            ->leftJoin('r.pros', 'p')
            ->leftjoin('r.cons', 'c')
            ->where('r.coaster = :coasterId')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setParameter('coasterId', $coaster->getId())
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get latest text reviews ordered by language
     * @param string $locale
     * @param int $limit
     * @return mixed
     */
    public function getLatestReviewsByLocale(string $locale = 'en', int $limit = 3)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->addSelect("CASE WHEN r.language = :locale THEN 0 ELSE 1 END AS HIDDEN languagePriority")
            ->addSelect('u')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.review is not null')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setMaxResults($limit)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $user
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getUserRatings(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r', 'm', 'c', 'p')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->join('r.user', 'u')
            ->join('r.coaster', 'c')
            ->join('c.manufacturer', 'm')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user);
    }

    /**
     * Get all reviews ordered by language
     * @param string $locale
     * @return array|mixed
     * @throws NonUniqueResultException
     */
    public function findAll(string $locale = 'en')
    {
        $count = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->where('r.review is not null')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r, u')
            ->addSelect("CASE WHEN r.language = :locale THEN 0 ELSE 1 END AS HIDDEN languagePriority")
            ->from('BddBundle:RiddenCoaster', 'r')
            ->join('r.user', 'u')
            ->where('r.review is not null')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setParameter('locale', $locale)
            ->getQuery()
            ->setHint('knp_paginator.count', $count);
    }

    /**
     * Update totalRating for all coasters
     *
     * @return bool
     */
    public function updateTotalRatings()
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
        } catch (DBALException $e) {
            return false;
        }

        return true;
    }

    /**
     * Update averageRating for all coasters
     * @param int $minRatings
     * @return bool|int
     */
    public function updateAverageRatings(int $minRatings)
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
            $statement = $connection->prepare($sql);
            $statement->execute(['minRatings' => $minRatings]);

            return $statement->rowCount();
        } catch (DBALException $e) {
            return false;
        }
    }

    /**
     * Get country where a user rode the most
     * @param User $user
     * @return mixed
     */
    public function findMostRiddenCountry(User $user)
    {
        $default = ['name' => '-'];
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('co.name as name')
                ->addSelect('count(1) as HIDDEN nb1')
                ->from('BddBundle:RiddenCoaster', 'r')
                ->join('r.coaster', 'c')
                ->join('c.park', 'p')
                ->join('p.country', 'co')
                ->where('r.user = :user')
                ->groupBy('co.id')
                ->orderBy('nb1', 'desc')
                ->setParameter('user', $user)
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
            return $default;
        } catch (NonUniqueResultException $e) {
            return $default;
        }
    }

    /**
     * Count ridden coasters for a user in Top 100
     * @param User $user
     * @return mixed
     */
    public function countTop100ForUser(User $user)
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_top100')
                ->from('BddBundle:RiddenCoaster', 'r')
                ->join('r.coaster', 'c')
                ->where('r.user = :user')
                ->andWhere('c.rank <= 100')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException $e) {
            return 0;
        }
    }

    /**
     * @param User $user
     * @return mixed|string
     */
    public function getMostRiddenManufacturer(User $user)
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb')
                ->addSelect('m.name as name')
                ->from('BddBundle:RiddenCoaster', 'r')
                ->join('r.coaster', 'c')
                ->join('c.manufacturer', 'm')
                ->where('r.user = :user')
                ->setParameter('user', $user)
                ->groupBy('m.id')
                ->orderBy('nb', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception $e) {
            return ['nb' => 0, 'name' => 'Unknown'];
        }
    }

    /**
     * @param User $user
     * @param int $max
     * @return mixed
     */
    public function findCoastersWithNoImage(User $user, int $max = 5)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->join('r.coaster', 'c')
            ->where('r.user = :user')
            ->andWhere('c.mainImage IS NULL')
            ->orderBy('c.totalRatings', 'desc')
            ->setFirstResult(rand(0, $max * 2))
            ->setMaxResults($max)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
