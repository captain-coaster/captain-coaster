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

    /** @return Query<mixed, mixed> */
    public function getAllUsersQuery(): Query
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.enabled = 1')
            ->andWhere('u.deletedAt IS NULL')
            ->getQuery();
    }

    /**
     * Every enabled, non-deleted user, streamed rather than hydrated all at
     * once — for broadcast fan-out (e.g. ranking notifications) where loading
     * tens of thousands of entities into memory up front isn't necessary.
     *
     * @return iterable<int, User>
     */
    public function findAllIterable(): iterable
    {
        foreach ($this->getAllUsersQuery()->toIterable() as $user) {
            yield $user;
        }
    }

    /**
     * The full aggregate (join every rating, group by user, sort by count)
     * has to run in full regardless of which page is requested -- no index
     * can pre-sort by an aggregate, so this scans and sorts all enabled
     * users on every call. The count is cached separately (a plain
     * COUNT(DISTINCT u.id) -- there's no HAVING threshold to replicate here,
     * unlike TopRepository::findAllTops()) and handed to KnpPaginator via a
     * hint; the main page-of-21 query isn't, since OFFSET grows with the
     * requested page.
     *
     * @return Query<mixed, mixed>
     */
    public function getAllUsersWithTotalRatingsQuery(): Query
    {
        $countQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(distinct u.id)')
            ->from(User::class, 'u')
            ->innerJoin('u.ratings', 'r')
            ->where('u.enabled = 1')
            ->getQuery();

        $countQuery->enableResultCache(300);
        $count = (int) $countQuery->getSingleScalarResult();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->addSelect('count(r.id) as total_ratings')
            ->from(User::class, 'u')
            ->where('u.enabled = 1')
            ->innerJoin('u.ratings', 'r', 'WITH', 'r.user = u')
            ->groupBy('r.user')
            ->orderBy('total_ratings', 'desc')
            ->getQuery()
            ->setHint('knp_paginator.count', $count);
    }

    /**
     * Returns users that have recently updated ratings or tops.
     *
     * Split into two single-join queries rather than one query with an OR spanning
     * both joins: that shape forces a join across every rating and every top per
     * user before the WHERE can filter anything, and prevents either side from
     * using an index on updatedAt. Each half here can.
     *
     * @return User[]
     */
    public function getUsersWithRecentRatingOrTopUpdate(int $sinceHours = 1): array
    {
        $date = new \DateTime('- '.$sinceHours.' hours');

        /** @var User[] $usersWithRecentRating */
        $usersWithRecentRating = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin('u.ratings', 'r')
            ->where('r.updatedAt > :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        /** @var User[] $usersWithRecentTop */
        $usersWithRecentTop = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->innerJoin('u.tops', 'l')
            ->where('l.updatedAt > :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        $usersById = [];
        foreach ([...$usersWithRecentRating, ...$usersWithRecentTop] as $user) {
            $usersById[$user->getId()] = $user;
        }

        return array_values($usersById);
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
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1) as nb_users')
                ->from(User::class, 'u')
                ->getQuery()
                ->getSingleScalarResult();
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

    /**
     * Find users banned before a given date who still have data to purge.
     *
     * @return User[]
     */
    public function findUsersBannedBefore(\DateTime $before): array
    {
        /** @var User[] $result */
        $result = $this->createQueryBuilder('u')
            ->leftJoin('u.ratings', 'r')
            ->where('u.bannedAt IS NOT NULL')
            ->andWhere('u.bannedAt <= :before')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('r.id IS NOT NULL')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
