<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Top;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Top>
 */
class TopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Top::class);
    }

    /**
     * Tops must have at lest 3 ranked coasters inside.
     *
     * Fetch-joins t.user (ManyToOne, safe alongside groupBy): Top/list.html.twig
     * reads top.user.displayName/.slug for every row, and unlike
     * user_tops/findAllByUser() -- where it's always the same, already-loaded
     * user -- this listing spans many different users. Does NOT fetch-join
     * topCoasters/coaster: that's a *-to-many join, and combining it with this
     * query's own GROUP BY/HAVING would force MySQL to collapse rows before
     * the collection can hydrate, returning only one arbitrary TopCoaster per
     * Top. Call preloadTopCoasters() on the page instead.
     *
     * The count is cached separately (a plain COUNT(DISTINCT) can't express
     * the ">2 coasters" HAVING, hence the correlated subquery) and handed to
     * KnpPaginator via a hint -- same reasoning as the other public listings:
     * every visitor shares one cache entry per language-independent query,
     * and it only changes when a top is created/edited.
     *
     * @return Query<mixed, mixed>
     */
    public function findAllTops(): Query
    {
        $countQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(t.id)')
            ->from(Top::class, 't')
            ->where('(SELECT COUNT(tc.id) FROM App\Entity\TopCoaster tc WHERE tc.top = t) > 2')
            ->getQuery();

        $countQuery->enableResultCache(300);
        $count = (int) $countQuery->getSingleScalarResult();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t')
            ->addSelect('u')
            ->addSelect('COUNT(t.id) as HIDDEN nb')
            ->from(Top::class, 't')
            ->innerJoin('t.user', 'u')
            ->join('t.topCoasters', 'tc')
            ->groupBy('t.id')
            ->having('nb > 2')
            ->orderBy('t.updatedAt', 'desc')
            ->getQuery()
            ->setHint('knp_paginator.count', $count);
    }

    /**
     * Bulk-populates topCoasters + coaster for a batch of already-loaded
     * tops, in one query -- avoids the per-top lazy load (and the EAGER
     * coaster's own cascade on top of it) that Top/list.html.twig and
     * User/tops.html.twig would otherwise trigger once per row.
     *
     * @param iterable<Top> $tops
     */
    public function preloadTopCoasters(iterable $tops): void
    {
        $ids = [];
        foreach ($tops as $top) {
            $ids[] = $top->getId();
        }

        if ([] === $ids) {
            return;
        }

        $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t', 'tc', 'c')
            ->from(Top::class, 't')
            ->join('t.topCoasters', 'tc')
            ->join('tc.coaster', 'c')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /** @return int|mixed */
    public function countTops()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(Top::class, 't')
                ->where('t.main = 1')
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * Return all lists for a user.
     *
     * @return array<int, Top>
     */
    public function findAllByUser(User $user): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t')
            ->from(Top::class, 't')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.main', 'desc')
            ->addOrderBy('t.updatedAt', 'desc')
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getTopWithData(Top $top): Top
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t', 'tc', 'c', 'm', 'p', 'co', 'mi')
            ->from(Top::class, 't')
            ->leftJoin('t.topCoasters', 'tc')
            ->leftJoin('tc.coaster', 'c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'co')
            ->leftJoin('c.manufacturer', 'm')
            ->leftJoin('c.mainImage', 'mi')
            ->where('t = :top')
            ->setParameter('top', $top)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Get user main top coasters for monthly ranking update.
     *
     * @return array<int, array{position: int, coaster: int}>
     */
    public function findUserTopForRanking(int $userId): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->addSelect('tc.position AS position', 'c.id as coaster')
            ->from(Top::class, 't')
            ->innerJoin('t.topCoasters', 'tc')
            ->innerJoin('tc.coaster', 'c')
            ->where('t.main = 1')
            ->andWhere('t.user = :id')
            ->andWhere('c.kiddie = 0')
            ->andWhere('c.holdRanking = 0')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getResult();
    }
}
