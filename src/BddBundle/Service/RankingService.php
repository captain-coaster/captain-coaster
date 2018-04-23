<?php

namespace BddBundle\Service;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\Liste;
use BddBundle\Entity\ListeCoaster;
use BddBundle\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class RankingService
 * @package BddBundle\Service
 */
class RankingService
{
    CONST MIN_DUELS = 5;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var array
     */
    private $duels = [];

    /**
     * @var array
     */
    private $ranking = [];

    /**
     * RankingService constructor.
     * @param EntityManagerInterface $em
     * @param NotificationService $notificationService
     */
    public function __construct(EntityManagerInterface $em, NotificationService $notificationService)
    {
        $this->em = $em;
        $this->notificationService = $notificationService;
    }

    /**
     * Update ranking of coasters
     * @param bool $dryRun
     * @return array
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function updateRanking(bool $dryRun = false): array
    {
        $this->computeRanking();

        $rank = 1;
        $infos = [];

        foreach ($this->ranking as $coasterId => $score) {
            $coaster = $this->em->getRepository('BddBundle:Coaster')->find($coasterId);
            $coaster->setScore($score);

            $coaster->setPreviousRank($coaster->getRank());
            $coaster->setRank($rank);

            $rank++;

            $infos[] = [
                $coaster->getName(),
                $coaster->getRank(),
                $coaster->getPreviousRank(),
                $coaster->getScore(),
            ];

            if ($dryRun) {
                continue;
            }

            $this->em->persist($coaster);

            if ($rank % 20) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (!$dryRun) {
            $this->notificationService->sendAll(
                'notif.ranking.message',
                NotificationService::NOTIF_RANKING
            );
        }

        return $infos;
    }

    /**
     * Compute ranking in ranking array
     */
    public function computeRanking(): void
    {
        $users = $this->em->getRepository('BddBundle:User')->findAll();

        foreach ($users as $user) {
            /** @var Liste $top */
            $top = $user->getMainListe();
            $this->processDuelsInTop($top);

            $ratings = $user->getRatings();
            $this->processDuelsInRatings($ratings);
        }

        $this->computeScore();
    }

    /**
     * Process duels and add result for a Top
     * @param Liste $top
     */
    private function processDuelsInTop(Liste $top): void
    {
        /** @var ListeCoaster $listeCoaster */
        foreach ($top->getListeCoasters() as $listeCoaster) {
            $coaster = $listeCoaster->getCoaster();

            if (!$coaster->isRankable()) {
                continue;
            }

            foreach ($top->getListeCoasters() as $listeCoasterDuel) {
                /** @var Coaster $duelCoaster */
                $duelCoaster = $listeCoasterDuel->getCoaster();

                if (!$duelCoaster->isRankable()) {
                    continue;
                }

                if ($coaster !== $duelCoaster) {
                    if ($listeCoaster->getPosition() < $listeCoasterDuel->getPosition()) {
                        $this->setWinner($coaster, $duelCoaster);
                    } else {
                        $this->setLooser($coaster, $duelCoaster);
                    }
                }
            }
        }
    }

    private function processDuelsInRatings($ratings)
    {
        /** @var RiddenCoaster $rating */
        foreach ($ratings as $rating) {
            $coaster = $rating->getCoaster();
            if (!$coaster->isRankable()) {
                continue;
            }

            /** @var RiddenCoaster $duelRating */
            foreach ($ratings as $duelRating) {
                $duelCoaster = $duelRating->getCoaster();
                if (!$duelCoaster->isRankable()) {
                    continue;
                }

                if ($coaster !== $duelCoaster) {
                    if ($rating->getValue() > $duelRating->getValue()) {
                        $this->setWinner($coaster, $duelCoaster);
                    } elseif ($rating->getValue() < $duelRating->getValue()) {
                        $this->setLooser($coaster, $duelCoaster);
                    }
                }
            }
        }
    }

    /**
     * Compute score based on "duels" array
     */
    private function computeScore()
    {
        foreach ($this->duels as $coasterId => $duels) {
            $duelScores = array_map(
                function ($value) {
                    if ($value == 0) {
                        return 50;
                    } elseif ($value > 0) {
                        return 100;
                    } else {
                        return 0;
                    }
                },
                $duels
            );

            $this->ranking[$coasterId] = array_sum($duelScores) / count($duelScores);
        }

        // Tri décroissant
        arsort($this->ranking);
    }

    /**
     * Set duel value for winning duel
     * @param $coaster
     * @param $duelCoaster
     */
    private function setWinner(Coaster $coaster, Coaster $duelCoaster): void
    {
        $this->setDuelResult($coaster, $duelCoaster, 1);
    }

    /**
     * Set duel value for losing duel
     * @param $coaster
     * @param $duelCoaster
     */
    private function setLooser(Coaster $coaster, Coaster $duelCoaster): void
    {
        $this->setDuelResult($coaster, $duelCoaster, -1);
    }

    /**
     * Set duel value
     * @param $coaster
     * @param $duelCoaster
     * @param $value
     */
    private function setDuelResult(Coaster $coaster, Coaster $duelCoaster, int $value): void
    {
        $coasterId = $coaster->getId();
        $duelCoasterId = $duelCoaster->getId();

        if (!array_key_exists($coasterId, $this->duels)) {
            $this->duels[$coasterId] = [];
        }

        if (!array_key_exists($duelCoasterId, $this->duels[$coasterId])) {
            $this->duels[$coasterId][$duelCoasterId] = $value;
        } else {
            $this->duels[$coasterId][$duelCoasterId] += $value;
        }
    }
}
