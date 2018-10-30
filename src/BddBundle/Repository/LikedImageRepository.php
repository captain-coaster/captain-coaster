<?php

namespace BddBundle\Repository;

use BddBundle\Entity\User;

class LikedImageRepository extends \Doctrine\ORM\EntityRepository
{
    public function findUserLikes(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i.id')
            ->from('BddBundle:LikedImage', 'li')
            ->join('li.image', 'i')
            ->where('li.user = :user')
            ->setParameter('user', $user)
            ->getQuery();
    }
}
