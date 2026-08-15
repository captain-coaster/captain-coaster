<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Country;
use App\Entity\Image;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Service\CaptainScore\UserCaptainScoreService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class StatService.
 */
class StatService
{
    /** RatingService constructor. */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserCaptainScoreService $userCaptainScoreService,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getIndexStats(): array
    {
        $stats = [];

        $stats['nb_ratings'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countAll();

        $date = new \DateTime();
        $date->sub(new \DateInterval('P1D'));
        $stats['nb_new_ratings'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countNew($date);

        $stats['nb_reviews'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countReviews();

        $stats['nb_images'] = $this->em
            ->getRepository(Image::class)
            ->countAll();

        return $stats;
    }

    /** @return array<string, mixed> */
    public function getUserStats(User $user): array
    {
        $stats = [];

        if (0 === $this->em->getRepository(RiddenCoaster::class)->countForUser($user)) {
            return $stats;
        }

        $stats['nb_coasters'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countForUser($user);
        $stats['nb_park'] = $this->em
            ->getRepository(Park::class)
            ->countForUser($user);
        $stats['nb_country'] = $this->em
            ->getRepository(Country::class)
            ->countForUser($user);
        $stats['country'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->findMostRiddenCountry($user);
        $top100 = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countTop100ForUser($user);

        if (\is_array($top100)) {
            $stats['top_100'] = $top100['nb_top100'];
            $stats['top_100_operating'] = (int) $top100['nb_top100_operating'];
        } else {
            $stats['top_100'] = 0;
            $stats['top_100_operating'] = 0;
        }

        $stats['manufacturer'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->getMostRiddenManufacturer($user);

        // Add favorite manufacturer from user's main top list (first 10-20 positions)
        $stats['top_rated_manufacturer'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->getTopListManufacturer($user, 10);

        return $stats;
    }

    /**
     * Score-driven rank tiers (lowest → highest). The cutoff is evaluated
     * against the Captain Score. Cutoffs are provisional — tune freely.
     */
    private const array RANK_TIERS = [
        ['key' => 'rookie', 'min' => 0],
        ['key' => 'enthusiast', 'min' => 50],
        ['key' => 'rider', 'min' => 150],
        ['key' => 'veteran', 'min' => 300],
        ['key' => 'expert', 'min' => 500],
        ['key' => 'legend', 'min' => 1000],
    ];

    /**
     * Rich, grouped stats for the redesigned profile page.
     * Returns an empty array for users who have not ridden anything yet.
     *
     * @return array<string, mixed>
     */
    public function getProfileStats(User $user): array
    {
        /** @var RiddenCoasterRepository $rc */
        $rc = $this->em->getRepository(RiddenCoaster::class);

        $ridden = $rc->countForUser($user);
        if (0 === $ridden) {
            return [];
        }

        $headline = $this->getUserStats($user);

        $cohort = $this->em->getRepository(Coaster::class)->findTop100CohortBounds();
        $top100Ridden = $rc->countRiddenInTop100Cohort($user, $cohort['cutoffRank']);

        $captainScores = $this->userCaptainScoreService->forUser($user);

        return [
            'headline' => $headline,
            'score' => [
                'ridden' => $ridden,
                'quality' => $captainScores->qualityScore,
                'strength' => $captainScores->strengthScore,
                'ranked_count' => $captainScores->coasterCount,
            ],
            'rank' => $this->computeRank($ridden),
            'this_year' => [
                'year' => (int) date('Y'),
                'new_coasters' => $rc->countNewCoastersThisYear($user),
                'total_rides' => $rc->countTotalRidesThisYear($user),
            ],
            'records' => [
                'tallest' => $rc->findUserSuperlativeByMetric($user, 'height'),
                'fastest' => $rc->findUserSuperlativeByMetric($user, 'speed'),
                'longest' => $rc->findUserSuperlativeByMetric($user, 'length'),
                'most_inversions' => $rc->findUserSuperlativeByMetric($user, 'inversionsNumber'),
                'oldest' => $rc->findUserCoasterByOpeningDate($user, 'ASC'),
                'newest' => $rc->findUserCoasterByOpeningDate($user, 'DESC'),
            ],
            'taste' => [
                'average_rating' => $rc->getUserAverageRating($user),
                'distribution' => $rc->getUserRatingDistribution($user),
                'rated_count' => $rc->countRatedForUser($user),
                'manufacturer' => $headline['manufacturer'] ?? null,
                'model' => $rc->getMostRiddenByVocabulary($user, 'model'),
                'country' => $headline['country'] ?? null,
            ],
            'progress' => [
                'top100_ridden' => $top100Ridden,
                'top100_size' => $cohort['size'],
            ],
            'milestone' => $this->computeMilestone($ridden),
        ];
    }

    /**
     * Captain Score — for now simply the number of coasters the user has ridden.
     *
     * TODO: replace with a richer quality-based formula
     * (e.g. derived from the global rank/score of the user's ridden coasters).
     */
    public function getQualityScore(User $user): int
    {
        return $this->em->getRepository(RiddenCoaster::class)->countForUser($user);
    }

    /**
     * Rank tier derived from the quality score, with progress toward the next tier.
     *
     * @return array{key: string, next_key: string|null, score: int, next_at: int|null, remaining: int, progress_pct: int}
     */
    public function computeRank(int $score): array
    {
        $currentIndex = 0;
        foreach (self::RANK_TIERS as $i => $tier) {
            if ($score >= $tier['min']) {
                $currentIndex = $i;
            }
        }

        $current = self::RANK_TIERS[$currentIndex];
        $next = self::RANK_TIERS[$currentIndex + 1] ?? null;

        if (null === $next) {
            return [
                'key' => $current['key'],
                'next_key' => null,
                'score' => $score,
                'next_at' => null,
                'remaining' => 0,
                'progress_pct' => 100,
            ];
        }

        $span = $next['min'] - $current['min'];
        $into = $score - $current['min'];

        return [
            'key' => $current['key'],
            'next_key' => $next['key'],
            'score' => $score,
            'next_at' => $next['min'],
            'remaining' => $next['min'] - $score,
            'progress_pct' => $span > 0 ? (int) round($into / $span * 100) : 0,
        ];
    }

    /**
     * Next ridden-coaster milestone: the first at 50, then every 100 (100, 200, 300…).
     * Always returns a next target.
     *
     * @return array{ridden: int, next: int, remaining: int, progress_pct: int}
     */
    private function computeMilestone(int $ridden): array
    {
        if ($ridden < 50) {
            $next = 50;
            $prev = 0;
        } else {
            $next = (intdiv($ridden, 100) + 1) * 100;
            $prev = 100 === $next ? 50 : $next - 100;
        }

        $span = $next - $prev;

        return [
            'ridden' => $ridden,
            'next' => $next,
            'remaining' => $next - $ridden,
            'progress_pct' => $span > 0 ? (int) round(($ridden - $prev) / $span * 100) : 0,
        ];
    }
}
