<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\TopCoaster;
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

    /** Count all coasters inside main tops only. */
    public function countAllInTops(): int
    {
        try {
            return (int) $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from(TopCoaster::class, 'tc')
                ->join('tc.top', 't')
                ->where('t.main = 1')
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
        $sql = '
            UPDATE coaster c
            JOIN (
                SELECT lc.coaster_id AS id, COUNT(1) AS nb
                FROM liste_coaster lc
                JOIN liste l ON l.id = lc.top_id
                WHERE l.main = 1
                GROUP BY lc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.total_tops_in = c2.nb
            ';

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
