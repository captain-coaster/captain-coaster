<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\DTO\Profile\ProfileRatings;
use App\DTO\Profile\ProfileRecord;
use App\DTO\Profile\ProfileStats;
use App\Entity\Coaster;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;

/**
 * Gathers what a member's profile page shows, applying the thresholds of each block.
 */
class ProfileStatsBuilder
{
    public const int FAVOURITES = 3;
    public const int RECORDS_MIN_RIDDEN = 10;
    public const int RATINGS_MIN_RIDDEN = 20;
    public const int YEARS = 10;
    /** A shorter review doesn't hold its place beside a photo: a second photo takes it. */
    public const int FEATURED_REVIEW_MIN_LENGTH = 300;

    /** Rides dated outside this range are typos in the data. */
    public const string FIRST_RIDE_DATE = '1950-01-01';
    /** Opening dates before this are placeholders. */
    public const string FIRST_OPENING_DATE = '1800-01-01';

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly CoasterRepository $coasterRepository,
        private readonly TopRepository $topRepository,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    public function build(User $user, \DateTimeImmutable $now): ProfileStats
    {
        $year = (int) $now->format('Y');
        $figures = $this->riddenCoasterRepository->countProfileFiguresForUser($user, $year);
        $ridden = $figures['ridden'];

        if (0 === $ridden) {
            return new ProfileStats(0, 0, 0, $year, 0, 0, [], null, 0, [], null, 0, 0, 0, 0, [], 0);
        }

        $favourites = $this->topRepository->findMainTopHead($user, self::FAVOURITES);
        $images = $this->imageRepository->countPhotosAndLikesForUser($user);
        $years = $this->yearCounts($this->riddenCoasterRepository->countRidesByDate($user), $now);
        $upvotes = $figures['upvotes'];
        $topReview = $upvotes > 0 ? $this->riddenCoasterRepository->findMostUpvotedReview($user, self::FEATURED_REVIEW_MIN_LENGTH) : null;

        return new ProfileStats(
            ridden: $ridden,
            parks: $figures['parks'],
            countries: $figures['countries'],
            year: $year,
            riddenInYear: $figures['riddenInYear'],
            top100: $this->riddenCoasterRepository->countTop100ForUser($user)['nb_top100_operating'],
            favourites: array_map(static fn (TopCoaster $topCoaster): Coaster => $topCoaster->getCoaster(), $favourites),
            mainTopId: [] === $favourites ? null : $favourites[0]->getTop()->getId(),
            tops: $this->topRepository->countForUser($user),
            records: $ridden >= self::RECORDS_MIN_RIDDEN ? $this->records($user) : [],
            ratings: $ridden >= self::RATINGS_MIN_RIDDEN ? $this->ratings($user) : null,
            reviews: $figures['reviews'],
            upvotes: $upvotes,
            photos: $images['photos'],
            likes: $images['likes'],
            years: $years['years'],
            undated: $ridden - $years['dated'],
            topReview: $topReview,
            topPhotos: $images['likes'] > 0 ? $this->imageRepository->findMostLikedForUser($user, null === $topReview ? 2 : 1) : [],
        );
    }

    /**
     * The extremes among the coasters the member rode, picked here from one row per coaster so the page loads the
     * record coasters in one query. Ties go to the best ranked, then the lowest id.
     *
     * @return list<ProfileRecord>
     */
    private function records(User $user): array
    {
        $facts = $this->riddenCoasterRepository->findRiddenCoasterFacts($user);
        $firstOpening = new \DateTimeImmutable(self::FIRST_OPENING_DATE);

        /** @var array<string, array{id: int, value: int}> $picks */
        $picks = [];
        $extremes = [
            ProfileRecord::TALLEST => 'height',
            ProfileRecord::FASTEST => 'speed',
            ProfileRecord::LONGEST => 'length',
            ProfileRecord::INVERSIONS => 'inversions',
        ];
        foreach ($extremes as $kind => $field) {
            $best = self::best($facts, static fn (array $fact): ?int => $fact[$field]);
            if (null === $best || ((int) $best[$field] <= 0 && ProfileRecord::INVERSIONS === $kind)) {
                continue;
            }

            $picks[$kind] = ['id' => $best['id'], 'value' => (int) $best[$field]];
        }

        // The earliest opening date scores highest.
        $oldest = self::best($facts, static fn (array $fact): ?int => null !== $fact['openingDate'] && $fact['openingDate'] >= $firstOpening ? -$fact['openingDate']->getTimestamp() : null);
        if (null !== $oldest) {
            $picks[ProfileRecord::OLDEST] = ['id' => $oldest['id'], 'value' => (int) $oldest['openingDate']->format('Y')];
        }

        $coasters = $this->coasterRepository->findWithParkAndImage(array_values(array_unique(array_column($picks, 'id'))));
        $records = [];
        foreach ($picks as $kind => $pick) {
            if (isset($coasters[$pick['id']])) {
                $records[] = new ProfileRecord($kind, $pick['value'], $coasters[$pick['id']]);
            }
        }

        return $records;
    }

    /**
     * @param list<array{id: int, height: ?int, speed: ?int, length: ?int, inversions: ?int, openingDate: ?\DateTimeInterface, worldRank: ?int}>           $facts
     * @param callable(array{id: int, height: ?int, speed: ?int, length: ?int, inversions: ?int, openingDate: ?\DateTimeInterface, worldRank: ?int}): ?int $score null leaves the coaster out
     *
     * @return ?array{id: int, height: ?int, speed: ?int, length: ?int, inversions: ?int, openingDate: ?\DateTimeInterface, worldRank: ?int}
     */
    private static function best(array $facts, callable $score): ?array
    {
        $best = null;
        $bestKey = null;
        foreach ($facts as $fact) {
            $value = $score($fact);
            if (null === $value) {
                continue;
            }

            // Highest score, then best rank (unranked last), then lowest id.
            $key = [-$value, $fact['worldRank'] ?? \PHP_INT_MAX, $fact['id']];
            if (null === $bestKey || $key < $bestKey) {
                $best = $fact;
                $bestKey = $key;
            }
        }

        return $best;
    }

    private function ratings(User $user): ProfileRatings
    {
        $counts = array_fill(0, 10, 0);
        foreach ($this->riddenCoasterRepository->countRatingsByValue($user) as $row) {
            $index = (int) round($row['value'] * 2) - 1;
            if ($index >= 0 && $index < 10) {
                $counts[$index] += $row['count'];
            }
        }

        $total = array_sum($counts);
        $sum = 0.0;
        foreach ($counts as $index => $count) {
            $sum += ($index + 1) / 2 * $count;
        }

        return new ProfileRatings($counts, $total > 0 ? round($sum / $total, 1) : 0.0);
    }

    /**
     * @param list<array{date: \DateTimeInterface, count: int}> $rides
     *
     * @return array{years: list<array{year: int, count: int}>, dated: int}
     */
    private function yearCounts(array $rides, \DateTimeImmutable $now): array
    {
        $first = new \DateTimeImmutable(self::FIRST_RIDE_DATE);
        $perYear = [];
        $dated = 0;
        foreach ($rides as $ride) {
            if ($ride['date'] < $first || $ride['date'] > $now) {
                continue;
            }

            $year = (int) $ride['date']->format('Y');
            $perYear[$year] = ($perYear[$year] ?? 0) + $ride['count'];
            $dated += $ride['count'];
        }

        if ([] === $perYear) {
            return ['years' => [], 'dated' => 0];
        }

        krsort($perYear);
        $recent = \array_slice(array_keys($perYear), 0, self::YEARS);
        $years = [];
        for ($year = min($recent); $year <= max($recent); ++$year) {
            $years[] = ['year' => $year, 'count' => $perYear[$year] ?? 0];
        }

        // Years far apart fill more than the chart holds: keep the latest.
        return ['years' => \array_slice($years, -self::YEARS), 'dated' => $dated];
    }
}
