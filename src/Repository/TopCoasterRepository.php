<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TopCoaster>
 */
class TopCoasterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TopCoaster::class);
    }

    /** @return mixed|null */
    public function countForCoaster(Coaster $coaster)
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(TopCoaster::class, 'l')
                ->where('l.coaster = :coaster')
                ->setParameter('coaster', $coaster)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    /** Count entries in a user's bucket list. */
    public function countBucketByUser(User $user): int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(TopCoaster::class, 'tc')
                ->join('tc.top', 't')
                ->where('t.user = :user')
                ->andWhere('t.type = :bucket')
                ->setParameter('user', $user)
                ->setParameter('bucket', Top::TYPE_BUCKET)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /**
     * Bucket-list entries for a user filtered by park, keyed by coaster id.
     *
     * @return array<int, TopCoaster>
     */
    public function findBucketByUserAndPark(User $user, Park $park): array
    {
        $entries = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('tc', 'c')
            ->from(TopCoaster::class, 'tc')
            ->join('tc.top', 't')
            ->join('tc.coaster', 'c')
            ->where('t.user = :user')
            ->andWhere('t.type = :bucket')
            ->andWhere('c.park = :park')
            ->setParameter('user', $user)
            ->setParameter('bucket', Top::TYPE_BUCKET)
            ->setParameter('park', $park)
            ->getQuery()
            ->getResult();

        $keyed = [];
        foreach ($entries as $entry) {
            $keyed[$entry->getCoaster()->getId()] = $entry;
        }

        return $keyed;
    }

    public function findBucketByUserAndCoaster(User $user, Coaster $coaster): ?TopCoaster
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('tc')
            ->from(TopCoaster::class, 'tc')
            ->join('tc.top', 't')
            ->where('t.user = :user')
            ->andWhere('t.type = :bucket')
            ->andWhere('tc.coaster = :coaster')
            ->setParameter('user', $user)
            ->setParameter('bucket', Top::TYPE_BUCKET)
            ->setParameter('coaster', $coaster)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Count all coasters inside ranking lists only. */
    public function countAllInTops(): int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(TopCoaster::class, 'tc')
                ->join('tc.top', 't')
                ->where('t.type = :type')
                ->setParameter('type', Top::TYPE_RANKING)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException) {
            return 0;
        }
    }

    /** Update totalTopsIn for all coasters. */
    public function updateTotalTopsIn(): bool
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = "
            UPDATE coaster c
            JOIN (
                SELECT lc.coaster_id AS id, COUNT(1) AS nb
                FROM liste_coaster lc
                JOIN liste l ON l.id = lc.top_id
                WHERE l.type = 'ranking'
                GROUP BY lc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.total_tops_in = c2.nb
            ";

        try {
            $connection->executeQuery($sql);
        } catch (DBALException) {
            return false;
        }

        return true;
    }

    /**
     * Update averageTopRank for all coasters.
     *
     * @return bool
     */
    public function updateAverageTopRanks(int $minTopsIn)
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = '
            UPDATE coaster c
            JOIN (
                SELECT lc.coaster_id AS id, FORMAT(AVG(position), 3) AS average
                FROM liste_coaster lc
                GROUP BY lc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.average_top_rank = c2.average
            WHERE c.total_tops_in >= :minTopsIn
            ';

        try {
            $connection->executeStatement($sql, ['minTopsIn' => $minTopsIn]);

            return true;
        } catch (DBALException) {
            return false;
        }
    }
}
