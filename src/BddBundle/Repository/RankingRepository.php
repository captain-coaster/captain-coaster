<?php

namespace BddBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * RankingRepository
 */
class RankingRepository extends EntityRepository
{
    /**
     * @return mixed|null
     */
    public function findCurrent()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('r')
                ->from('BddBundle:Ranking', 'r')
                ->orderBy('r.computedAt', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {
            return null;
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }
}
