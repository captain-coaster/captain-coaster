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

    /** Check if a user has liked an image */
    public function hasUserLiked(User $user, $image): bool
    {
        $result = $this->createQueryBuilder('li')
            ->select('COUNT(li.id)')
            ->where('li.user = :user')
            ->andWhere('li.image = :image')
            ->setParameter('user', $user)
            ->setParameter('image', $image)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /** Recalculate like counters for all images in one efficient SQL query */
    public function updateAllLikeCounts(): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            UPDATE image i
            LEFT JOIN (
                SELECT image_id, COUNT(*) as like_count
                FROM liked_image
                GROUP BY image_id
            ) li ON i.id = li.image_id
            SET i.like_counter = COALESCE(li.like_count, 0)
        ';

        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        // Clear the entity manager to ensure entities are refreshed
        $this->getEntityManager()->clear();
    }
}
