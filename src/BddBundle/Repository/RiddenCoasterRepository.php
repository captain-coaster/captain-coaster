<?php

namespace BddBundle\Repository;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\User;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * RiddenCoasterRepository
 */
class RiddenCoasterRepository extends EntityRepository
{
    /**
     * Count all ratings
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAll()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1) as nb_rating')
            ->from('BddBundle:RiddenCoaster', 'r')
            ->getQuery()
            ->getSingleScalarResult();
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
            ->select('r')
            ->addSelect('p')
            ->addSelect('c')
            ->addSelect('u')
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
     * Get all reviews ordered by language
     * @param string $locale
     * @return array|mixed
     */
    public function findAll(string $locale = 'en')
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
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * Update totalRating for a specific coaster
     * @param Coaster $coaster
     * @return bool
     */
    public function updateTotalRating(Coaster $coaster)
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
            WHERE c.id = :coasterId
            ';

        try {
            $statement = $connection->prepare($sql);
            $statement->execute(['coasterId' => $coaster->getId()]);
        } catch (DBALException $e) {
            return false;
        }

        return true;
    }

    /**
     * Update averageRating for a specific coaster
     * @param Coaster $coaster
     * @return bool
     */
    public function updateAverageRating(Coaster $coaster)
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
            WHERE c.id = :coasterId
            ';

        try {
            $statement = $connection->prepare($sql);
            $statement->execute(['coasterId' => $coaster->getId()]);
        } catch (DBALException $e) {
            return false;
        }

        return true;
    }
}
