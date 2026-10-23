<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\ImageReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImageReport>
 */
class ImageReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageReport::class);
    }

    /** Whether an unresolved report already exists for this image (dedup for reprocess re-runs). */
    public function hasUnresolvedReport(Image $image): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.image = :image')
            ->andWhere('r.resolved = :resolved')
            ->setParameter('image', $image)
            ->setParameter('resolved', false)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /**
     * Reports waiting for a moderator, oldest first, with their photo and coaster.
     *
     * @return list<ImageReport>
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('i', 'c')
            ->leftJoin('r.image', 'i')
            ->leftJoin('i.coaster', 'c')
            ->where('r.status = :pending')
            ->setParameter('pending', ImageReport::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return $this->count(['status' => ImageReport::STATUS_PENDING]);
    }
}
