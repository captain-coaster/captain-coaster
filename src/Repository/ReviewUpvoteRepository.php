<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReviewUpvote;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewUpvote>
 */
class ReviewUpvoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewUpvote::class);
    }

    /** Check if a user has already upvoted a review */
    public function hasUserUpvoted(User $user, RiddenCoaster $review): bool
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.user = :user')
            ->andWhere('u.review = :review')
            ->setParameter('user', $user)
            ->setParameter('review', $review)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /** Count upvotes for a review */
    public function countUpvotesForReview(RiddenCoaster $review): int
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.review = :review')
            ->setParameter('review', $review)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
