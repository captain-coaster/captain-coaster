<?php

namespace BddBundle\Service;

use BddBundle\Entity\Coaster;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class TopService
 * @package BddBundle\Service
 */
class TopService
{
    CONST MIN_TOPS = 2;

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
     * Update totalTopsIn & averageTopRank for a coaster
     * @param Coaster $coaster
     */
    public function updateTopStats(Coaster $coaster): void
    {
        $repo = $this->em->getRepository('BddBundle:ListeCoaster');

        if ($repo->countForCoaster($coaster) >= self::MIN_TOPS) {
            $repo->updateAverageTopRank($coaster);
        }

        $repo->updateTotalTopsIn($coaster);
    }
}
