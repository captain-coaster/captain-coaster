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
     * @return Query
     */
    public function findAllTops()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t')
            ->addSelect('COUNT(t.id) as HIDDEN nb')
            ->from(Top::class, 't')
            ->join('t.topCoasters', 'tc')
            ->groupBy('t.id')
            ->having('nb > 2')
            ->orderBy('t.updatedAt', 'desc')
            ->getQuery();
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

    /** @return Query */
    public function findAllCustomLists()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t')
            ->addSelect('COUNT(t.id) as HIDDEN nb')
            ->from(Top::class, 't')
            ->join('t.topCoasters', 'tc')
            ->where('t.main = 0')
            ->groupBy('t.id')
            ->having('nb > 2')
            ->orderBy('t.updatedAt', 'desc')
            ->getQuery();
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
            ->select('t', 'tc', 'c', 'm', 'p', 'co')
            ->from(Top::class, 't')
            ->leftJoin('t.topCoasters', 'tc')
            ->leftJoin('tc.coaster', 'c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'co')
            ->leftJoin('c.manufacturer', 'm')
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
