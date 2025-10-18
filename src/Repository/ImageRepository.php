<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
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

    public function findLatestImage()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->andWhere('i.credit is not null')
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult();
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
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.enabled = 1')
            ->getQuery()
            ->getSingleScalarResult();
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

    public function findWatermarkToFix(?int $limit, int $offset = 0): array
    {
        $queryBuilder = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('i')
            ->from(Image::class, 'i')
            ->where('i.watermarked = true')
            ->andWhere('i.id BETWEEN 16749 AND 23361')
            ->orderBy('i.id', 'ASC');

        if ($offset > 0) {
            $queryBuilder->setFirstResult($offset);
        }

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countWatermarkToFix(): int
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.watermarked = true')
            ->andWhere('i.id BETWEEN 16749 AND 23361')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
