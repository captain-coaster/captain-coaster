<?php

namespace BddBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * ImageRepository
 */
class ImageRepository extends EntityRepository
{
    /**
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findLatestImage()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from('BddBundle:Image', 'i')
            ->where('i.enabled = 1')
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
    }
}
