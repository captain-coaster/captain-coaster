<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LikedImage>
 */
class LikedImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LikedImage::class);
    }

    public function findUserLikes(User $user): Query
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
    public function hasUserLiked(User $user, Image $image): bool
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

    /** Recalculate like counters for all images in one efficient SQL query (excludes disabled users) */
    public function updateAllLikeCounts(): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            UPDATE image i
            LEFT JOIN (
                SELECT li.image_id, COUNT(*) as like_count
                FROM liked_image li
                INNER JOIN users u ON li.user_id = u.id
                WHERE u.enabled = 1
                GROUP BY li.image_id
            ) li ON i.id = li.image_id
            SET i.like_counter = COALESCE(li.like_count, 0)
        ';

        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        // Clear the entity manager to ensure entities are refreshed
        $this->getEntityManager()->clear();
    }
}
