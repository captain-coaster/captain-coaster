<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Top;
use App\Entity\TopLike;
use App\Entity\User;
use App\Repository\TopLikeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Toggle and query likes on (custom) Top Lists.
 *
 * Likes apply to custom lists only — ranking and bucket lists are user-personal.
 */
class TopLikeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopLikeRepository $repository,
    ) {
    }

    public function canLike(Top $top, ?User $user = null): bool
    {
        if (!$top->isCustom()) {
            return false;
        }

        // Users cannot like their own lists.
        return null === $user || $user !== $top->getUser();
    }

    /**
     * Toggle a like. Returns the new state.
     *
     * @return array{liked: bool, count: int}
     */
    public function toggle(User $user, Top $top): array
    {
        if (!$this->canLike($top, $user)) {
            return ['liked' => false, 'count' => $top->getLikesCount()];
        }

        $existing = $this->repository->findOneByUserAndTop($user, $top);

        if (null !== $existing) {
            $this->em->remove($existing);
            $top->decrementLikesCount();
            $this->em->flush();

            return ['liked' => false, 'count' => $top->getLikesCount()];
        }

        $like = new TopLike($user, $top);
        $this->em->persist($like);
        $top->incrementLikesCount();
        $this->em->flush();

        return ['liked' => true, 'count' => $top->getLikesCount()];
    }

    public function hasLiked(User $user, Top $top): bool
    {
        return null !== $this->repository->findOneByUserAndTop($user, $top);
    }
}
