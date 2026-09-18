<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\ImageReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
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

    /** @return Query<mixed, mixed> */
    public function findUnresolved(): Query
    {
        return $this->createQueryBuilder('r')
            ->where('r.resolved = false')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery();
    }
}
