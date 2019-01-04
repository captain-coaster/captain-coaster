<?php

namespace App\Repository;

use App\Entity\User;

class LikedImageRepository extends \Doctrine\ORM\EntityRepository
{
    public function findUserLikes(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i.id')
            ->from('App:LikedImage', 'li')
            ->join('li.image', 'i')
            ->where('li.user = :user')
            ->setParameter('user', $user)
            ->getQuery();
    }
}
