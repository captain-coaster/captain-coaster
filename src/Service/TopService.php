<?php

namespace App\Service;

use App\Entity\TopCoaster;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class TopService
 * @package App\Service
 */
class TopService
{
    CONST MIN_TOPS_IN = 2;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * TopService constructor.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Update totalTopsIn & averageTopRank for all coasters
     *
     * @return int
     */
    public function updateTopStats(): int
    {
        $repo = $this->em->getRepository(TopCoaster::class);

        $repo->updateTotalTopsIn();

        return $repo->updateAverageTopRanks(self::MIN_TOPS_IN);
    }
}
