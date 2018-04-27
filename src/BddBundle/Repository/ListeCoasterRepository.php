<?php

namespace BddBundle\Repository;

use BddBundle\Entity\Coaster;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;

/**
 * ListCoasterRepository
 */
class ListeCoasterRepository extends EntityRepository
{
    /**
     * @param Coaster $coaster
     * @return mixed|null
     */
    public function countForCoaster(Coaster $coaster)
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('count(1)')
                ->from('BddBundle:ListeCoaster', 'l')
                ->where('l.coaster = :coaster')
                ->setParameter('coaster', $coaster)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * Update totalTopsIn for a specific coaster
     * @param Coaster $coaster
     * @return bool
     */
    public function updateTotalTopsIn(Coaster $coaster)
    {
        $connection = $this->getEntityManager()->getConnection();
        $sql = '
            UPDATE coaster c
            JOIN (
                SELECT lc.coaster_id AS id, COUNT(1) AS nb
                FROM liste_coaster lc
                GROUP BY lc.coaster_id
            ) c2
            ON c2.id = c.id
            SET c.total_tops_in = c2.nb
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
     * Update averageTopRank for a specific coaster
     * @param Coaster $coaster
     * @return bool
     */
    public function updateAverageTopRank(Coaster $coaster)
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
