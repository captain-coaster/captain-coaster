<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Reports waiting for a moderator, oldest first, with the review, its author and coaster,
     * and the member who reported it.
     *
     * @return list<ReviewReport>
     */
    public function findPending(): array
    {
        return $this->queueQuery()
            ->where('r.status = :pending')
            ->setParameter('pending', ReviewReport::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * What the AI hid without a moderator since then, newest first: for spot checks.
     *
     * @return list<ReviewReport>
     */
    public function findAutoHiddenSince(\DateTimeInterface $since): array
    {
        return $this->queueQuery()
            ->where('r.status = :autoHidden')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('autoHidden', ReviewReport::STATUS_AUTO_HIDDEN)
            ->setParameter('since', $since)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return $this->count(['status' => ReviewReport::STATUS_PENDING]);
    }

    private function queueQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->addSelect('rc', 'author', 'c', 'reporter')
            ->leftJoin('r.review', 'rc')
            ->leftJoin('rc.user', 'author')
            ->leftJoin('rc.coaster', 'c')
            ->leftJoin('r.user', 'reporter');
    }

    /**
     * Reports on this review a moderator's decision settles: pending, and those the AI auto-hid.
     *
     * @return ReviewReport[]
     */
    public function findActionableReportsForReview(RiddenCoaster $review): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.review = :review')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('review', $review)
            ->setParameter('statuses', [ReviewReport::STATUS_PENDING, ReviewReport::STATUS_AUTO_HIDDEN])
            ->getQuery()
            ->getResult();
    }

    /** Whether a report on this review, from a member or the AI, waits for a moderator. */
    public function hasUnresolvedReport(RiddenCoaster $review): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.review = :review')
            ->andWhere('r.resolved = :resolved')
            ->setParameter('review', $review)
            ->setParameter('resolved', false)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /** Whether an unresolved, AI-generated (system) report already exists for this review. */
    public function hasUnresolvedAiReport(RiddenCoaster $review): bool
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.review = :review')
            ->andWhere('r.user IS NULL')
            ->andWhere('r.resolved = :resolved')
            ->setParameter('review', $review)
            ->setParameter('resolved', false)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }
}
