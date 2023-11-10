<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TopCoaster;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class TopService.
 */
class TopService
{
    final public const MIN_TOPS_IN = 2;

    /**
     * TopService constructor.
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Update totalTopsIn & averageTopRank for all coasters.
     */
    public function updateTopStats(): int
    {
        $repo = $this->em->getRepository(TopCoaster::class);

        $repo->updateTotalTopsIn();

        return $repo->updateAverageTopRanks(self::MIN_TOPS_IN);
    }
}
