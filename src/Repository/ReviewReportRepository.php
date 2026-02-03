<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewReport>
 */
class ReviewReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewReport::class);
    }

    /** Check if a user has already reported a review */
    public function hasUserReported(User $user, RiddenCoaster $review): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.review = :review')
            ->setParameter('user', $user)
            ->setParameter('review', $review)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /** Count reports for a review */
    public function countReportsForReview(RiddenCoaster $review): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.review = :review')
            ->setParameter('review', $review)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /** Find unresolved reports */
    public function findUnresolvedReports(): Query
    {
        return $this->createQueryBuilder('r')
            ->where('r.resolved = :resolved')
            ->setParameter('resolved', false)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery();
    }

    /** Find reports by reason */
    public function findByReason(string $reason): Query
    {
        return $this->createQueryBuilder('r')
            ->where('r.reason = :reason')
            ->setParameter('reason', $reason)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery();
    }

    /**
     * Find all pending reports for a specific review.
     *
     * @return ReviewReport[]
     */
    public function findPendingReportsForReview(RiddenCoaster $review): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.review = :review')
            ->andWhere('r.status = :status')
            ->setParameter('review', $review)
            ->setParameter('status', ReviewReport::STATUS_PENDING)
            ->getQuery()
            ->getResult();
    }
}
