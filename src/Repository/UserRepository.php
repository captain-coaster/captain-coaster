<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
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

    /**
     * Returns users that have recently updated ratings or tops.
     *
     * @return User[]
     */
    public function getUsersWithRecentRatingOrTopUpdate(int $sinceHours = 1): array
    {
        $date = new \DateTime('- '.$sinceHours.' hours');

        /** @var User[] $result */
        $result = $this->getEntityManager()
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

        return $result;
    }

    /** @return array<int, array{name: string, slug: string}> */
    public function getAllForSearch(): array
    {
        /** @var array<int, array{name: string, slug: string}> $result */
        $result = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u.displayName as name')
            ->addSelect('u.slug')
            ->from(User::class, 'u')
            ->where('u.enabled = 1')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Count all users. */
    public function countAll(): int
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_users')
                ->from(User::class, 'u')
                ->getQuery();

            $query->enableResultCache(600);

            return (int) $query->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * Optimized search method for API with limited results and better performance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBySearchQuery(string $query, int $limit = 5): array
    {
        /** @var array<int, array<string, mixed>> $result */
        $result = $this->createQueryBuilder('u')
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
            ->enableResultCache(300)
            ->getArrayResult();

        return $result;
    }

    /** @return User[] */
    public function findUsersScheduledForDeletion(\DateTime $before): array
    {
        /** @var User[] $result */
        $result = $this->createQueryBuilder('u')
            ->where('u.deletedAt IS NOT NULL')
            ->andWhere('u.deletedAt <= :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
