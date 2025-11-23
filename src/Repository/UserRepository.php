<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function getAllUsersQuery(): Query
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->getQuery();
    }

    public function getAllUsersWithTotalRatingsQuery(): Query
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->addSelect('count(r.id) as total_ratings')
            ->from(User::class, 'u')
            ->where('u.enabled = 1')
            ->innerJoin('u.ratings', 'r', 'WITH', 'r.user = u')
            ->groupBy('r.user')
            ->orderBy('total_ratings', 'desc')
            ->getQuery();
    }

    /** Returns users that have recently up. */
    public function getUsersWithRecentRatingOrTopUpdate(int $sinceHours = 1)
    {
        $date = new \DateTime('- '.$sinceHours.' hours');

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->leftJoin('u.ratings', 'r')
            ->leftJoin('u.tops', 'l')
            ->where('r.updatedAt > :date')
            ->orWhere('l.updatedAt > :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function getAllForSearch()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u.displayName as name')
            ->addSelect('u.slug')
            ->from(User::class, 'u')
            ->where('u.enabled = 1')
            ->getQuery()
            ->getResult();
    }

    /** Count all users. */
    public function countAll()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_users')
                ->from(User::class, 'u')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /** Optimized search method for API with limited results and better performance. */
    public function findBySearchQuery(string $query, int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id', 'u.displayName as name', 'u.slug', 'COUNT(r.id) as totalRatings')
            ->leftJoin('u.ratings', 'r')
            ->where('u.enabled = 1')
            ->andWhere('u.displayName LIKE :query OR u.slug LIKE :slugQuery')
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('slugQuery', '%'.str_replace(' ', '-', $query).'%')
            ->groupBy('u.id')
            ->orderBy('totalRatings', 'DESC')
            ->addOrderBy('u.displayName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(300) // Cache for 5 minutes
            ->getArrayResult();
    }
}
