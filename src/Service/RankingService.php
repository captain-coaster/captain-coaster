<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Ranking;
use App\Entity\RiddenCoaster;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class RankingService
{
    // Minimum comparison number between coaster A and B
    final public const MIN_COMPARISONS = 3;
    // Minimum duels for a coaster, i.e. minimum number of other coasters to be compared with
    final public const MIN_DUELS = 325;
    // For elite coaster, we need more comparisons
    final public const ELITE_SCORE = 95;
    final public const MIN_DUELS_ELITE_SCORE = 550;
    private array $duels = [];
    private array $ranking = [];
    private int $totalComparisonNumber = 0;
    private array $userComparisons = [];
    private array $rejectedCoasters = [];
    private ?string $highlightedNewCoaster = null;

    public function __construct(private readonly EntityManagerInterface $em, private readonly UserRepository $userRepository)
    {
    }

    /**
     * Update ranking of coasters.
     *
     * @throws \Exception
     */
    public function updateRanking(bool $dryRun = false): array
    {
        $this->computeRanking($dryRun);

        $rank = 1;
        $coasterList = [];

        foreach ($this->ranking as $coasterId => $score) {
            $coaster = $this->em->getRepository(Coaster::class)->find($coasterId);

            if (null === $coaster->getRank() && $rank < 300 && null === $this->highlightedNewCoaster) {
                $this->highlightedNewCoaster = $coaster->getName();
            }

            $coaster->setScore((string) $score);
            $coaster->setPreviousRank($coaster->getRank());
            $coaster->setRank($rank);
            $coaster->setUpdatedAt(new \DateTime());

            // used just for command output
            $coasterList[] = $coaster;

            ++$rank;

            if ($dryRun) {
                continue;
            }

            $this->em->persist($coaster);

            if (0 !== $rank % 20) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (!$dryRun) {
            // create new ranking entry in database
            $this->createRankingEntry();
            // remove coasters not ranked anymore
            $this->disableNonRankedCoasters();
        }

        return $coasterList;
    }

    /** Compute ranking in ranking array. */
    public function computeRanking(bool $dryRun): void
    {
        $users = $this->userRepository->findAll();

        /** @var User $user */
        foreach ($users as $user) {
            // reset before each user
            $this->userComparisons = [];

            $this->processComparisonsInTop($this->em->getRepository(Top::class)->findUserTopForRanking($user->getId()));
            $this->processComparisonsInRatings($this->em->getRepository(RiddenCoaster::class)->findUserRatingsForRanking($user->getId()));
        }

        $this->computeScore($dryRun);
    }

    public function getHighlightedNewCoaster(): ?string
    {
        return $this->highlightedNewCoaster;
    }

    /** Process all comparisons inside a top and set all results in duels array. */
    private function processComparisonsInTop(array $top): void
    {
        foreach ($top as $topCoaster) {
            $coaster = $topCoaster['coaster'];

            foreach ($top as $comparedTopCoaster) {
                $comparedCoaster = $comparedTopCoaster['coaster'];

                if ($coaster !== $comparedCoaster) {
                    // add this comparison to user comparisons array
                    $this->userComparisons[$coaster.'-'.$comparedCoaster] = 1;

                    if ($topCoaster['position'] < $comparedTopCoaster['position']) {
                        $this->setWinner($coaster, $comparedCoaster);
                    } else {
                        $this->setLooser($coaster, $comparedCoaster);
                    }
                }
            }
        }
    }

    /** Process all comparisons of all rated coaster for a user and set all results in duels array. */
    private function processComparisonsInRatings(array $ratings): void
    {
        foreach ($ratings as $rating) {
            $coaster = $rating['coaster'];

            foreach ($ratings as $comparedRating) {
                $comparedCoaster = $comparedRating['coaster'];

                if ($coaster !== $comparedCoaster) {
                    // check if comparison already exists in Top for this user
                    if (\array_key_exists($coaster.'-'.$comparedCoaster, $this->userComparisons)) {
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
     * A duel is the result of all comparisons for coaster A and B.
     */
    private function computeScore(bool $dryRun): void
    {
        $this->rejectedCoasters = [];

        $this->computeRejectedCoasters();

        $i = 1;
        while (\count($this->rejectedCoasters) > 0 && $i < 5) {
            $this->removeRejectedCoasters();
            $this->computeRejectedCoasters();
            ++$i;
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
                    if ((floor($comparisonResult + $reverseComparisonResult) - ($comparisonResult + $reverseComparisonResult)) < 0) {
                        dump(floor($comparisonResult + $reverseComparisonResult) - ($comparisonResult + $reverseComparisonResult));
                        exit;
                    }
                    $this->totalComparisonNumber += (int) ($comparisonResult + $reverseComparisonResult);
                    ++$duelCount;

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

            if (!$dryRun) {
                // update duel stat
                $this->updateDuelStat($coasterId, $duelCount);
            }
        }

        // sort in reverse order (higher score is first)
        arsort($this->ranking);
    }

    /** Compute a list of coaster that does not meet the comparison & duel requirements. */
    private function computeRejectedCoasters(): void
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
                    ++$duelCount;
                }
            }

            if ($duelCount < self::MIN_DUELS) {
                $this->rejectedCoasters[] = $coasterId;
            }
        }
    }

    /** Remove all duels from rejected coasters. */
    private function removeRejectedCoasters(): void
    {
        foreach ($this->rejectedCoasters as $idRejected) {
            unset($this->duels[$idRejected]);
            foreach ($this->duels as $checkCurrentId => $checkDuels) {
                if (\array_key_exists($idRejected, $checkDuels)) {
                    unset($this->duels[$checkCurrentId][$idRejected]);
                }
            }
        }

        $this->rejectedCoasters = [];
    }

    /** Set result for winning comparison. */
    private function setWinner(int $coasterId, int $comparedCoasterId): void
    {
        $this->setComparisonResult($coasterId, $comparedCoasterId, 1);
    }

    /** Set result for losing comparison. */
    private function setLooser(int $coasterId, int $comparedCoasterId): void
    {
        $this->setComparisonResult($coasterId, $comparedCoasterId, 0);
    }

    /** Set result for tie comparison (same rating). */
    private function setTie(int $coasterId, int $comparedCoasterId): void
    {
        $this->setComparisonResult($coasterId, $comparedCoasterId, 0.5);
    }

    /** Set comparison result. */
    private function setComparisonResult(int $coasterId, int $comparedCoasterId, float $value): void
    {
        if (!isset($this->duels[$coasterId][$comparedCoasterId])) {
            $this->duels[$coasterId][$comparedCoasterId] = $value;
        } else {
            $this->duels[$coasterId][$comparedCoasterId] += $value;
        }
    }

    /** Remove rank and previous_rank fields for coaster not ranked anymore. */
    private function disableNonRankedCoasters(): void
    {
        $sql = 'update coaster c
                set c.rank = NULL, c.previous_rank = NULL, c.score = NULL, c.valid_duels = 0
                where c.updated_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)
                and c.rank is not NULL;';

        try {
            $this->em->getConnection()->prepare($sql)->executeStatement();
        } catch (\Throwable $e) {
            // todo log
        }
    }

    /** Add a row for Ranking entity in database. */
    private function createRankingEntry(): void
    {
        $ranking = new Ranking();

        $ranking->setRatingNumber($this->em->getRepository(RiddenCoaster::class)->countAll());
        $ranking->setTopNumber($this->em->getRepository(Top::class)->countTops());
        $ranking->setUserNumber($this->userRepository->count([]));
        $ranking->setCoasterInTopNumber($this->em->getRepository(TopCoaster::class)->countAllInTops());
        $ranking->setComparisonNumber($this->totalComparisonNumber);
        $ranking->setRankedCoasterNumber(\count($this->ranking));

        $this->em->persist($ranking);
        $this->em->flush();
    }

    /** Update "validDuels" column for a coaster. */
    private function updateDuelStat(int $coasterId, int $duelCount): void
    {
        $sql = 'update coaster c
                set c.valid_duels = :count
                where c.id = :id;';

        try {
            $this->em->getConnection()->prepare($sql)
                ->executeStatement([
                    ':count' => $duelCount,
                    ':id' => $coasterId,
                ]);
        } catch (\Throwable $e) {
            // todo log
        }
    }
}
