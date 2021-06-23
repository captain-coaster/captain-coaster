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
    const MIN_COMPARISONS = 3;
    // Minimum duels for a coaster, i.e. minimum number of other coasters to be compared with
    const MIN_DUELS = 275;
    // For elite coaster, we need more comparisons
    const ELITE_SCORE = 99;
    const MIN_DUELS_ELITE_SCORE = 350;

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
            // reset before each user
            $this->userComparisons = [];

            $this->processComparisonsInTop(
                $this->em->getRepository(Top::class)->findUserTopForRanking($user->getId())
            );
            $this->processComparisonsInRatings(
                $this->em->getRepository(RiddenCoaster::class)->findUserRatingsForRanking($user->getId())
            );
        }

        $this->computeScore();
    }

    /**
     * Process all comparisons inside a top and set all results in duels array
     *
     * @param array $top
     */
    private function processComparisonsInTop(array $top): void
    {
        foreach ($top as $topCoaster) {
            $coaster = $topCoaster['coaster'];

            foreach ($top as $comparedTopCoaster) {
                $comparedCoaster = $comparedTopCoaster['coaster'];

                if ($coaster !== $comparedCoaster) {
                    // add this comparison to user comparisons array
                    $this->userComparisons[$coaster . '-' . $comparedCoaster] = 1;

                    if ($topCoaster['position'] < $comparedTopCoaster['position']) {
                        $this->setWinner($coaster, $comparedCoaster);
                    } else {
                        $this->setLooser($coaster, $comparedCoaster);
                    }
                }
            }
        }
    }

    /**
     * Process all comparisons of all rated coaster for a user and set all results in duels array
     *
     * @param array $ratings
     */
    private function processComparisonsInRatings(array $ratings)
    {
        foreach ($ratings as $rating) {
            $coaster = $rating['coaster'];

            foreach ($ratings as $comparedRating) {
                $comparedCoaster = $comparedRating['coaster'];

                if ($coaster !== $comparedCoaster) {
                    // check if comparison already exists in Top for this user
                    if (array_key_exists($coaster . '-' . $comparedCoaster, $this->userComparisons)) {
                        continue;
                    }

                    if ($rating['rating'] > $comparedRating['rating']) {
                        $this->setWinner($coaster, $comparedCoaster);
                    } elseif ($rating['rating'] < $comparedRating['rating']) {
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
     * @param int $coasterId
     * @param int $comparedCoasterId
     */
    private function setWinner(int $coasterId, int $comparedCoasterId): void
    {
        $this->setComparisonResult($coasterId, $comparedCoasterId, 1);
    }

    /**
     * Set result for losing comparison
     *
     * @param int $coasterId
     * @param int $comparedCoasterId
     */
    private function setLooser(int $coasterId, int $comparedCoasterId): void
    {
        $this->setComparisonResult($coasterId, $comparedCoasterId, 0);
    }

    /**
     * Set result for tie comparison (same rating)
     *
     * @param int $coasterId
     * @param int $comparedCoasterId
     */
    private function setTie(int $coasterId, int $comparedCoasterId): void
    {
        $this->setComparisonResult($coasterId, $comparedCoasterId, 0.5);
    }

    /**
     * Set comparison result
     *
     * @param int $coasterId
     * @param int $comparedCoasterId
     * @param float $value
     */
    private function setComparisonResult(int $coasterId, int $comparedCoasterId, float $value): void
    {
        if (!isset($this->duels[$coasterId][$comparedCoasterId])) {
            $this->duels[$coasterId][$comparedCoasterId] = $value;
        } else {
            $this->duels[$coasterId][$comparedCoasterId] += $value;
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
