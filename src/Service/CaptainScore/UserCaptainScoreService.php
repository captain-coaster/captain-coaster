<?php

declare(strict_types=1);

namespace App\Service\CaptainScore;

use App\Entity\User;
use App\Repository\RiddenCoasterRepository;

/**
 * Computes Captain Quality Score (CQS) and Captain Strength Score (CSS)
 * for a rider — based on the captain ranking score of the coasters they
 * have ridden. Per spec, only ranked coasters count for riders; kiddies
 * and unranked coasters are excluded.
 *
 * Computed on the fly for now (no caching) — n is at most a few hundred
 * coasters per user and the math is cheap.
 */
class UserCaptainScoreService
{
    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly CaptainScoreCalculator $calculator,
    ) {
    }

    public function forUser(User $user): CaptainScores
    {
        $scores = $this->riddenCoasterRepository->findRankedCoasterScoresForUser($user);

        return $this->calculator->compute($scores);
    }
}
