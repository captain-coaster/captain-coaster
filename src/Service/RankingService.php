<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Ranking;
use App\Event\RankingPublishedEvent;
use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopCoasterRepository;
use App\Repository\TopRepository;
use App\Repository\UserRepository;
use App\Service\Ranking\RankingCalculator;
use App\Service\Ranking\RankingReport;
use App\Service\Ranking\RankingResult;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The monthly ranking, in three steps: compute() it from every rider's ratings and Top, stage() it as a pending
 * Ranking with its RankingHistory rows, then publish() it at a fixed time, once for everyone.
 */
class RankingService
{
    // Rankings are published on the 1st at noon UTC: the 1st of the month almost everywhere, daytime in Europe
    final public const string PUBLICATION_TIME = '12:00';
    private const int INSERT_BATCH = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RankingRepository $rankingRepository,
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly TopRepository $topRepository,
        private readonly TopCoasterRepository $topCoasterRepository,
        private readonly UserRepository $userRepository,
        private readonly CoasterRepository $coasterRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /** Reads every rating and main Top, then runs RankingCalculator: nothing is written. */
    public function compute(): RankingResult
    {
        $calculator = new RankingCalculator();
        $tops = $this->topRepository->findTopsForRanking();

        // Ratings come sorted by user: a rider is complete when the next one starts
        $user = null;
        $ratings = [];
        foreach ($this->riddenCoasterRepository->iterateRatingsForRanking() as [$rider, $coaster, $rating]) {
            if ((int) $rider !== $user) {
                if (null !== $user) {
                    $calculator->addRider($ratings, $tops[$user] ?? []);
                    unset($tops[$user]);
                }
                $user = (int) $rider;
                $ratings = [];
            }
            $ratings[(int) $coaster] = (float) $rating;
        }
        if (null !== $user) {
            $calculator->addRider($ratings, $tops[$user] ?? []);
            unset($tops[$user]);
        }

        // Riders with a Top but no rating
        foreach ($tops as $top) {
            $calculator->addRider([], $top);
        }

        return $calculator->compute();
    }

    /** Compares a computed ranking with the one it would replace. */
    public function report(RankingResult $result, \DateTimeImmutable $month): RankingReport
    {
        $previous = $this->rankingRepository->findPublishedBefore($month);

        return RankingReport::compare(
            $result->ranks(),
            $previous ? $this->rankingRepository->findRanks($previous) : [],
            $result->comparisons,
            $previous?->getComparisonNumber() ?? 0,
        );
    }

    /**
     * Saves a computed ranking as the pending Ranking of $month, in one transaction.
     *
     * @param array<string, mixed> $run duration, memory... stored with the report
     *
     * @throws \RuntimeException when $month already has a ranking and $replace is false
     */
    public function stage(RankingResult $result, RankingReport $report, \DateTimeImmutable $month, array $run, bool $replace): Ranking
    {
        return $this->em->wrapInTransaction(function () use ($result, $report, $month, $run, $replace): Ranking {
            $existing = $this->rankingRepository->findOneBy(['month' => $month]);
            if (null !== $existing) {
                if (!$replace) {
                    throw new \RuntimeException(\sprintf('%s already has a ranking (#%d, %s).', $month->format('F Y'), $existing->getId(), $existing->getPublishedAt() ? 'published' : 'pending'));
                }
                // Its RankingHistory rows go with it (ON DELETE CASCADE)
                $this->em->remove($existing);
                $this->em->flush();
            }

            $ranking = new Ranking($month);
            $ranking->setRatingNumber($this->riddenCoasterRepository->countAll());
            $ranking->setTopNumber((int) $this->topRepository->countTops());
            $ranking->setUserNumber($this->userRepository->count(['enabled' => true]));
            $ranking->setCoasterInTopNumber($this->topCoasterRepository->countAllInTops());
            $ranking->setComparisonNumber($result->comparisons);
            $ranking->setRankedCoasterNumber(\count($result->coasters));
            $ranking->setFeaturedDuel($result->featuredDuel);
            $ranking->setReport($report->toArray() + $run + [
                'contributors' => $result->contributors,
                'thresholds' => [
                    'minComparisons' => RankingCalculator::MIN_COMPARISONS,
                    'minDuels' => RankingCalculator::MIN_DUELS,
                    'eliteScore' => RankingCalculator::ELITE_SCORE,
                    'minDuelsElite' => RankingCalculator::MIN_DUELS_ELITE,
                ],
            ]);
            $this->em->persist($ranking);
            $this->em->flush();

            $this->insertHistory($ranking->getId(), $result);

            return $ranking;
        });
    }

    /**
     * Copies a staged ranking into the coasters' rank columns, then clears the caches and notifies riders.
     * Previous ranks come from the ranking published before it, so publishing twice changes nothing.
     */
    public function publish(Ranking $ranking): void
    {
        $previous = $this->rankingRepository->findPublishedBefore($ranking->getMonth());

        $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use ($ranking, $previous): void {
            $connection = $em->getConnection();
            $params = ['ranking' => $ranking->getId(), 'previous' => $previous?->getId() ?? 0];

            // Every coaster ranked before or now; the others have no rank already
            $connection->executeStatement(
                'UPDATE coaster c
                LEFT JOIN ranking_history h ON h.coaster_id = c.id AND h.ranking_id = :ranking
                LEFT JOIN ranking_history p ON p.coaster_id = c.id AND p.ranking_id = :previous
                SET c.`rank` = h.`rank`,
                    c.previous_rank = IF(h.id IS NULL, NULL, p.`rank`),
                    c.score = h.score,
                    c.valid_duels = COALESCE(h.validDuels, 0)
                WHERE c.`rank` IS NOT NULL OR h.id IS NOT NULL',
                $params,
            );

            // Best rank ever, and the month it was first reached: an equal rank keeps the first month
            $connection->executeStatement(
                'UPDATE coaster c
                JOIN ranking_history h ON h.coaster_id = c.id AND h.ranking_id = :ranking
                SET c.best_rank = h.`rank`, c.best_rank_at = :month
                WHERE c.best_rank IS NULL OR h.`rank` < c.best_rank',
                ['ranking' => $ranking->getId(), 'month' => $ranking->getMonth()->format('Y-m-d H:i:s')],
            );

            $ranking->setPublishedAt(new \DateTimeImmutable());
            $em->flush();
        });

        $this->eventDispatcher->dispatch(new RankingPublishedEvent($this->coasterRepository->getNewlyRankedHighlightedCoaster()?->getName()));
    }

    /** When the ranking of $month is published. */
    public static function publicationTime(\DateTimeInterface $month): \DateTimeImmutable
    {
        return new \DateTimeImmutable($month->format('Y-m-01 ').self::PUBLICATION_TIME, new \DateTimeZone('UTC'));
    }

    /** The next publication after $now. */
    public static function nextPublication(\DateTimeInterface $now): \DateTimeImmutable
    {
        $thisMonth = self::publicationTime($now);

        return $thisMonth > $now ? $thisMonth : self::publicationTime($thisMonth->modify('first day of next month'));
    }

    /** First day of $now's month, in UTC: the month a ranking computed at $now belongs to. */
    public static function monthOf(\DateTimeInterface $now): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($now)->setTimezone(new \DateTimeZone('UTC'))->modify('first day of this month midnight');
    }

    /**
     * Comparisons a rider feeds the ranking: every pair within their Top, plus every pair of rated coasters not
     * already settled by the Top (both in it), as RankingCalculator does.
     *
     * @param int $both Top coasters the rider also rated
     */
    public static function riderComparisons(int $ratings, int $top, int $both): int
    {
        $pairs = static fn (int $n): int => intdiv($n * ($n - 1), 2);

        return $pairs($top) + $pairs($ratings) - $pairs($both);
    }

    /** RankingHistory rows, with a snapshot of each coaster's rating and Top stats. */
    private function insertHistory(int $rankingId, RankingResult $result): void
    {
        $connection = $this->em->getConnection();

        foreach (array_chunk($result->coasters, self::INSERT_BATCH) as $offset => $chunk) {
            $values = [];
            $params = [];
            foreach ($chunk as $index => $row) {
                $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
                array_push($params, $rankingId, $row->coaster, $offset * self::INSERT_BATCH + $index + 1, (string) $row->score, $row->duels, $row->won, $row->lost, $row->tied, $row->riders);
            }

            $connection->executeStatement(
                'INSERT INTO ranking_history (ranking_id, coaster_id, `rank`, score, validDuels, won, lost, tied, riders) VALUES '.implode(', ', $values),
                $params,
            );
        }

        $connection->executeStatement(
            'UPDATE ranking_history h JOIN coaster c ON c.id = h.coaster_id
            SET h.totalTopsIn = c.total_tops_in, h.averageTopRank = c.average_top_rank,
                h.totalRatings = c.total_ratings, h.averageRating = c.averageRating
            WHERE h.ranking_id = :ranking',
            ['ranking' => $rankingId],
        );
    }
}
