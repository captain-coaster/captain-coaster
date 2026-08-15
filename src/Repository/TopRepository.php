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
     * Public lists for the Explore page.
     * - ranking & bucket lists: always public
     * - custom lists: only if is_public = 1
     * - excludes lists with fewer than 3 coasters
     * - optional type filter (ranking|bucket|custom)
     * - optional case-insensitive search across list name and owner display name.
     *
     * @return Query<mixed, mixed>
     */
    public function findPublicTops(?string $type = null, ?string $query = null): Query
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t', 'u')
            ->addSelect('COUNT(tc.id) as HIDDEN nb')
            ->from(Top::class, 't')
            ->join('t.user', 'u')
            ->join('t.topCoasters', 'tc')
            ->where('(t.type IN (:alwaysPublic) OR (t.type = :customType AND t.isPublic = true))')
            ->setParameter('alwaysPublic', [Top::TYPE_RANKING, Top::TYPE_BUCKET])
            ->setParameter('customType', Top::TYPE_CUSTOM)
            ->groupBy('t.id')
            ->having('nb > 2')
            ->orderBy('t.updatedAt', 'desc');

        if (null !== $type) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('LOWER(t.name) LIKE :q OR LOWER(u.displayName) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($query)).'%');
        }

        return $qb->getQuery();
    }

    /**
     * Count public lists per type, for filter-pill badges on Explore.
     *
     * @return array<string, int>
     */
    public function countPublicTopsByType(): array
    {
        $sql = <<<'SQL'
            SELECT sub.type AS type, COUNT(*) AS nb
            FROM (
                SELECT l.id, l.type
                FROM liste l
                INNER JOIN liste_coaster lc ON lc.top_id = l.id
                WHERE l.type IN ('ranking', 'bucket')
                   OR (l.type = 'custom' AND l.is_public = 1)
                GROUP BY l.id, l.type
                HAVING COUNT(lc.id) > 2
            ) sub
            GROUP BY sub.type
            SQL;

        /** @var list<array{type: string, nb: int|string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql);

        $out = [Top::TYPE_RANKING => 0, Top::TYPE_BUCKET => 0, Top::TYPE_CUSTOM => 0];
        foreach ($rows as $row) {
            $out[$row['type']] = (int) $row['nb'];
        }

        return $out;
    }

    /** @return int|mixed */
    public function countTops()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(Top::class, 't')
                ->where('t.type = :type')
                ->setParameter('type', Top::TYPE_RANKING)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * Return all lists for a user, ranking list first.
     *
     * @return array<int, Top>
     */
    public function findAllByUser(User $user): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('t')
            ->addSelect('CASE WHEN t.type = :ranking THEN 0 WHEN t.type = :bucket THEN 1 ELSE 2 END AS HIDDEN ord')
            ->from(Top::class, 't')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->setParameter('ranking', Top::TYPE_RANKING)
            ->setParameter('bucket', Top::TYPE_BUCKET)
            ->orderBy('ord', 'asc')
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
            ->where('t.type = :type')
            ->setParameter('type', Top::TYPE_RANKING)
            ->andWhere('t.user = :id')
            ->andWhere('c.kiddie = 0')
            ->andWhere('c.holdRanking = 0')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getResult();
    }
}
