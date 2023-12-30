<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LikedImage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LikedImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LikedImage::class);
    }

    public function findUserLikes(User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i.id')
            ->from(LikedImage::class, 'li')
            ->join('li.image', 'i')
            ->where('li.user = :user')
            ->setParameter('user', $user)
            ->getQuery();
    }
}
