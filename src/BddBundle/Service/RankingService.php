<?php

namespace BddBundle\Service;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\Liste;
use BddBundle\Entity\ListeCoaster;
use BddBundle\Entity\Ranking;
use BddBundle\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class RankingService
 * @package BddBundle\Service
 */
class RankingService
{
    // Total ratings + tops for a coaster
    CONST MIN_RATINGS_PLUS_TOPS = 5;
    // Minimum comparison number between coaster A and B
    CONST MIN_COMPARISONS = 3;
    // Minimum duels for a coaster, i.e. minimum number of
    // other coasters to be compared with
    CONST MIN_DUELS = 150;

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
     * @var integer
     */
    private $totalComparisonNumber = 0;

    /**
     * RankingService constructor
     *
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
     *
     * @param bool $dryRun
     * @return array
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

            // used just for command output
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
            // create new ranking entry in database
            $this->createRankingEntry();
            // remove coasters not ranked anymore
            $this->disableNonRankedCoasters();
            // send notifications to everyone
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
            $this->processComparisonsInTop($top);

            $ratings = $user->getRatings();
            $this->processComparisonsInRatings($ratings);
        }

        $this->computeScore();
    }

    /**
     * Process all comparisons inside a top
     * and set all results in duels array
     *
     * @param Liste $top
     */
    private function processComparisonsInTop(Liste $top): void
    {
        /** @var ListeCoaster $listeCoaster */
        foreach ($top->getListeCoasters() as $listeCoaster) {
            $coaster = $listeCoaster->getCoaster();

            if (!$coaster->isRankable()) {
                continue;
            }

            foreach ($top->getListeCoasters() as $comparedListeCoaster) {
                /** @var Coaster $comparedCoaster */
                $comparedCoaster = $comparedListeCoaster->getCoaster();

                if (!$comparedCoaster->isRankable()) {
                    continue;
                }

                if ($coaster !== $comparedCoaster) {
                    if ($listeCoaster->getPosition() < $comparedListeCoaster->getPosition()) {
                        $this->setWinner($coaster, $comparedCoaster);
                    } else {
                        $this->setLooser($coaster, $comparedCoaster);
                    }
                }
            }
        }
    }

    /**
     * Process all comparisons of all rated coaster for a user
     * and set all results in duels array
     *
     * @param $ratings
     */
    private function processComparisonsInRatings(iterable $ratings)
    {
        /** @var RiddenCoaster $rating */
        foreach ($ratings as $rating) {
            $coaster = $rating->getCoaster();

            if (!$coaster->isRankable()) {
                continue;
            }

            /** @var RiddenCoaster $comparedRating */
            foreach ($ratings as $comparedRating) {
                $comparedCoaster = $comparedRating->getCoaster();

                if (!$comparedCoaster->isRankable()) {
                    continue;
                }

                if ($coaster !== $comparedCoaster) {
                    if ($rating->getValue() > $comparedRating->getValue()) {
                        $this->setWinner($coaster, $comparedCoaster);
                    } elseif ($rating->getValue() < $comparedRating->getValue()) {
                        $this->setLooser($coaster, $comparedCoaster);
                    } else {
                        $this->setTie($coaster, $comparedCoaster);
                    }
                }
            }
        }
    }

    /**
     * Compute score based on duels array
     * A duel is the result of all comparisons for coaster A and B
     */
    private function computeScore()
    {
        foreach ($this->duels as $coasterId => $coasterDuels) {
            $duelScoreSum = 0;
            $duelCount = 0;
            foreach ($coasterDuels as $duelCoasterId => $comparisonResult) {
                // if $comparisonResult is result of A compared to B
                // $reverseComparisonResult is result of B compared to A
                $reverseComparisonResult = $this->duels[$duelCoasterId][$coasterId];

                // don't take into account if too few comparisons
                // $comparisonResult + $reverseComparisonResult always equals vote number
                if ($comparisonResult + $reverseComparisonResult >= self::MIN_COMPARISONS) {
                    $this->totalComparisonNumber += ($comparisonResult + $reverseComparisonResult);
                    $duelCount++;

                    // same win & loose numbers
                    if ($comparisonResult === $reverseComparisonResult) {
                        $duelScoreSum += 50;
                        // $coaster has more wins
                    } elseif ($comparisonResult > $reverseComparisonResult) {
                        $duelScoreSum += 100;
                        // $coaster has less wins
                    } else {
                        $duelScoreSum += 0;
                    }
                }
            }

            if ($duelCount > self::MIN_DUELS) {
                // final score is between 0 and 100
                $this->ranking[$coasterId] = $duelScoreSum / $duelCount;
            }
        }

        // sort in reverse order (higher score is first)
        arsort($this->ranking);
    }

    /**
     * Set result for winning comparison
     *
     * @param $coaster
     * @param $comparedCoaster
     */
    private function setWinner(Coaster $coaster, Coaster $comparedCoaster): void
    {
        $this->setComparisonResult($coaster, $comparedCoaster, 1);
    }

    /**
     * Set result for losing comparison
     *
     * @param $coaster
     * @param $comparedCoaster
     */
    private function setLooser(Coaster $coaster, Coaster $comparedCoaster): void
    {
        $this->setComparisonResult($coaster, $comparedCoaster, 0);
    }

    /**
     * Set result for tie comparison (same rating)
     *
     * @param $coaster
     * @param $comparedCoaster
     */
    private function setTie(Coaster $coaster, Coaster $comparedCoaster): void
    {
        $this->setComparisonResult($coaster, $comparedCoaster, 0.5);
    }

    /**
     * Set comparison result
     *
     * @param $coaster
     * @param $comparedCoaster
     * @param $value
     */
    private function setComparisonResult(Coaster $coaster, Coaster $comparedCoaster, float $value): void
    {
        $coasterId = $coaster->getId();
        $duelCoasterId = $comparedCoaster->getId();

        if (!array_key_exists($coasterId, $this->duels)) {
            $this->duels[$coasterId] = [];
        }

        if (!array_key_exists($duelCoasterId, $this->duels[$coasterId])) {
            $this->duels[$coasterId][$duelCoasterId] = $value;
        } else {
            $this->duels[$coasterId][$duelCoasterId] += $value;
        }
    }

    /**
     * Remove rank and previous_rank fields for coaster not ranked anymore
     */
    private function disableNonRankedCoasters()
    {
        $conn = $this->em->getConnection();
        $sql = 'update coaster c
                set c.rank = NULL and c.previous_rank = NULL
                where c.updated_at < DATE_SUB(NOW(), INTERVAL 3 WEEK)
                and c.rank is not NULL;';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } catch (\Exception $e) {
            // do nothing
        }
    }

    /**
     *
     */
    private function createRankingEntry()
    {
        $ranking = new Ranking();

        $ranking->setRatingNumber($this->em->getRepository('BddBundle:RiddenCoaster')->countAll());
        $ranking->setTopNumber($this->em->getRepository('BddBundle:Liste')->countTops());
        $ranking->setUserNumber($this->em->getRepository('BddBundle:User')->count([]));
        $ranking->setCoasterInTopNumber($this->em->getRepository('BddBundle:ListeCoaster')->countAllInTops());
        $ranking->setComparisonNumber($this->totalComparisonNumber);
        $ranking->setRankedCoasterNumber(count($this->ranking));

        $this->em->persist($ranking);
        $this->em->flush();
    }
}
