<?php

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Ranking;
use App\Entity\RiddenCoaster;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class RankingService
 * @package App\Service
 */
class RankingService
{
    // Minimum comparison number between coaster A and B
    CONST MIN_COMPARISONS = 3;
    // Minimum duels for a coaster, i.e. minimum number of other coasters to be compared with
    CONST MIN_DUELS = 250;
    // For elite coaster, we need more comparisons
    CONST ELITE_SCORE = 99;
    CONST MIN_DUELS_ELITE_SCORE = 500;

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
     * @var array
     */
    private $userComparisons = [];

    /**
     * @var array
     */
    private $rejectedCoasters = [];

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
     * @throws \Exception
     */
    public function updateRanking(bool $dryRun = false): array
    {
        $this->computeRanking();

        $rank = 1;
        $infos = [];

        foreach ($this->ranking as $coasterId => $score) {
            $coaster = $this->em->getRepository(Coaster::class)->find($coasterId);

            $coaster->setScore($score);
            $coaster->setPreviousRank($coaster->getRank());
            $coaster->setRank($rank);
            $coaster->setUpdatedAt(new \DateTime());

            // used just for command output
            $infos[] = $coaster;

            $rank++;

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
        $users = $this->em->getRepository(User::class)->findAll();

        /** @var User $user */
        foreach ($users as $user) {
            // reset before each new user
            $this->userComparisons = [];

            $top = $user->getMainTop();
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
     * @param Top $top
     */
    private function processComparisonsInTop(Top $top): void
    {
        foreach ($top->getTopCoasters() as $topCoaster) {
            $coaster = $topCoaster->getCoaster();

            if (!$coaster->isRankable()) {
                continue;
            }

            foreach ($top->getTopCoasters() as $comparedTopCoaster) {
                $comparedCoaster = $comparedTopCoaster->getCoaster();

                if (!$comparedCoaster->isRankable()) {
                    continue;
                }

                if ($coaster !== $comparedCoaster) {
                    // add this comparison to user comparisons array
                    $this->userComparisons[$coaster->getId().'-'.$comparedCoaster->getId()] = 1;

                    if ($topCoaster->getPosition() < $comparedTopCoaster->getPosition()) {
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
                    // check if comparison alreay exists in Top for this user
                    if (array_key_exists($coaster->getId().'-'.$comparedCoaster->getId(), $this->userComparisons)) {
                        continue;
                    }

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
        $this->rejectedCoasters = [];

        $this->computeRejectedCoasters();

        $i = 1;
        while (count($this->rejectedCoasters) > 0 || $i > 5) {
            $this->removeRejectedCoasters();
            $this->computeRejectedCoasters();
            $i++;
        }

        $this->ranking = [];
        $this->totalComparisonNumber = 0;

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

            if ($duelCount >= self::MIN_DUELS) {
                // final score is between 0 and 100
                $finalScore = $duelScoreSum / $duelCount;

                // Elite coaster has specific minimum duels
                if ($finalScore < self::ELITE_SCORE || $duelCount >= self::MIN_DUELS_ELITE_SCORE) {
                    $this->ranking[$coasterId] = $finalScore;
                }
            }

            // update duel stat
            $this->updateDuelStat($coasterId, $duelCount);
        }

        // sort in reverse order (higher score is first)
        arsort($this->ranking);
    }

    /**
     * Compute a list of coaster that does not meet the comparison & duel requirements
     */
    private function computeRejectedCoasters()
    {
        foreach ($this->duels as $coasterId => $coasterDuels) {
            $duelCount = 0;
            foreach ($coasterDuels as $duelCoasterId => $comparisonResult) {
                // if $comparisonResult is result of A compared to B
                // $reverseComparisonResult is result of B compared to A
                $reverseComparisonResult = $this->duels[$duelCoasterId][$coasterId];

                // don't take into account if too few comparisons
                // $comparisonResult + $reverseComparisonResult always equals vote number
                if ($comparisonResult + $reverseComparisonResult >= self::MIN_COMPARISONS) {
                    $duelCount++;
                }
            }

            if ($duelCount < self::MIN_DUELS) {
                $this->rejectedCoasters[] = $coasterId;
            }
        }
    }

    /**
     * Remove all duels from rejected coasters
     */
    private function removeRejectedCoasters()
    {
        foreach ($this->rejectedCoasters as $idRejected) {
            unset($this->duels[$idRejected]);
            foreach ($this->duels as $checkCurrentId => $checkDuels) {
                if (array_key_exists($idRejected, $checkDuels)) {
                    unset($this->duels[$checkCurrentId][$idRejected]);
                }
            }
        }

        $this->rejectedCoasters = [];
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
                set c.rank = NULL, c.previous_rank = NULL, c.score = NULL, c.valid_duels = NULL
                where c.updated_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)
                and c.rank is not NULL;';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } catch (\Exception $e) {
            // do nothing
        }
    }

    /**
     * Add a row for Ranking entity in database
     */
    private function createRankingEntry()
    {
        $ranking = new Ranking();

        $ranking->setRatingNumber($this->em->getRepository(RiddenCoaster::class)->countAll());
        $ranking->setTopNumber($this->em->getRepository(Top::class)->countTops());
        $ranking->setUserNumber($this->em->getRepository(User::class)->count([]));
        $ranking->setCoasterInTopNumber($this->em->getRepository(TopCoaster::class)->countAllInTops());
        $ranking->setComparisonNumber($this->totalComparisonNumber);
        $ranking->setRankedCoasterNumber(count($this->ranking));

        $this->em->persist($ranking);
        $this->em->flush();
    }

    /**
     * Update "validDuels" column for a coaster
     *
     * @param int $coasterId
     * @param int $duelCount
     */
    private function updateDuelStat(int $coasterId, int $duelCount)
    {
        $conn = $this->em->getConnection();
        $sql = 'update coaster c
                set c.valid_duels = :count
                where c.id = :id;';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':count', $duelCount);
            $stmt->bindParam(':id', $coasterId);
            $stmt->execute();
        } catch (\Exception $e) {
            // do nothing
        }
    }
}
