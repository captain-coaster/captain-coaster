<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function findLatestLikedImage()
    {
        $query = $this->createQueryBuilder('i')
            ->join(LikedImage::class, 'li', 'WITH', 'li.image = i.id')
            ->where('i.enabled = 1')
            ->andWhere('i.credit IS NOT NULL')
            ->orderBy('li.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        $query->enableResultCache(300);

        return $query->getSingleResult();
    }

    public function findUserImages(User $user): Query
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->andWhere('i.credit is not null')
            ->andWhere('i.uploader = :uploader')
            ->setParameter('uploader', $user->getId())
            ->getQuery();
    }

    public function countUserEnabledImages(User $user): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->andWhere('i.credit is not null')
            ->andWhere('i.uploader = :uploader')
            ->setParameter('uploader', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->getQuery();

        $query->enableResultCache(600);

        return $query->getSingleScalarResult();
    }

    public function findImageToBeValidated(): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from(Image::class, 'i')
            ->where('i.enabled = 0')
            ->andWhere('i.createdAt < :date')
            ->setParameter('date', new \DateTime('-23 hours'))
            ->getQuery()
            ->getResult();
    }
}
