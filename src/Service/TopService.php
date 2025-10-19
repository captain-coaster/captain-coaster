<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TopCoasterRepository;

class TopService
{
    final public const MIN_TOPS_IN = 2;

    public function __construct(private readonly TopCoasterRepository $topCoasterRepository)
    {
    }

    /** Update totalTopsIn & averageTopRank for all coasters. */
    public function updateTopStats(): bool
    {
        $this->topCoasterRepository->updateTotalTopsIn();

        return $this->topCoasterRepository->updateAverageTopRanks(self::MIN_TOPS_IN);
    }
}
