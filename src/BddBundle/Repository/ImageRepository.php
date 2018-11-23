<?php

namespace BddBundle\Repository;

use BddBundle\Entity\User;
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
            ->andWhere('i.credit is not null')
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * @param User $user
     * @return mixed
     */
    public function findUserImages(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from('BddBundle:Image', 'i')
            ->where('i.enabled = 1')
            ->andWhere('i.credit is not null')
            ->andWhere('i.uploader = :uploader')
            ->setParameter('uploader', $user->getId())
            ->getQuery();
    }

    /**
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAll()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from('BddBundle:Image', 'i')
            ->where('i.enabled = 1')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
