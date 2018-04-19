<?php

namespace BddBundle\Repository;

use BddBundle\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * RiddenCoasterRepository
 */
class RiddenCoasterRepository extends EntityRepository
{
    /**
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
            ->where('r.createdAt > ?1')
            ->setParameter(1, $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
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
     * @param int    $coasterId
     * @param string $locale
     * @return mixed
     */
    public function getReviews(int $coasterId, $locale = 'en')
    {
        // add joins to avoid multiple subqueries
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('r')
            ->addSelect('p')
            ->addSelect('c')
            ->addSelect('u')
            ->addSelect("CASE WHEN r.language = :locale AND r.review IS NOT NULL THEN 0 ELSE 1 END AS HIDDEN languagePriority")
            ->from('BddBundle:RiddenCoaster', 'r')
            ->innerJoin('r.user', 'u')
            ->leftJoin('r.pros', 'p')
            ->leftjoin('r.cons', 'c')
            ->where('r.coaster = :coasterId')
            ->orderBy('languagePriority', 'asc')
            ->addOrderBy('r.updatedAt', 'desc')
            ->setParameter('coasterId', $coasterId)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string $locale
     * @param int    $limit
     * @return mixed
     */
    public function getLatestReviewsByLocale($locale = 'en', $limit = 3)
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
     * @param string $locale
     * @return array|mixed
     */
    public function findAll($locale = 'en')
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
}
