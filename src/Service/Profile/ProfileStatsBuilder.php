<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\DTO\Profile\ProfileRatings;
use App\DTO\Profile\ProfileRecord;
use App\DTO\Profile\ProfileStats;
use App\Entity\Coaster;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Repository\CountryRepository;
use App\Repository\ImageRepository;
use App\Repository\ParkRepository;
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
    public const int TAGS = 3;
    public const int YEARS = 10;

    /** Rides dated outside this range are typos in the data. */
    public const string FIRST_RIDE_DATE = '1950-01-01';
    /** Opening dates before this are placeholders. */
    public const string FIRST_OPENING_DATE = '1800-01-01';

    // The coaster table has a few wrong heights, so a MAX() over it can't give the world record yet.
    public const int WORLD_RECORD_HEIGHT = 195;
    public const int WORLD_RECORD_SPEED = 250;
    public const int WORLD_RECORD_LENGTH = 4250;
    public const int WORLD_RECORD_INVERSIONS = 14;

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly ParkRepository $parkRepository,
        private readonly CountryRepository $countryRepository,
        private readonly TopRepository $topRepository,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    public function build(User $user, \DateTimeImmutable $now): ProfileStats
    {
        $year = (int) $now->format('Y');
        $figures = $this->riddenCoasterRepository->countFiguresForUser($user, $year);
        $ridden = $figures['ridden'];

        if (0 === $ridden) {
            return new ProfileStats(0, 0, 0, $year, 0, 0, [], null, 0, [], null, 0, 0, 0, 0, [], 0);
        }

        $favourites = $this->topRepository->findMainTopHead($user, self::FAVOURITES);
        $images = $this->imageRepository->countPhotosAndLikesForUser($user);
        $years = $this->yearCounts($this->riddenCoasterRepository->countRidesByDate($user), $now);

        return new ProfileStats(
            ridden: $ridden,
            parks: $this->parkRepository->countForUser($user),
            countries: $this->countryRepository->countForUser($user),
            year: $year,
            riddenInYear: $figures['riddenInYear'],
            top100: $this->riddenCoasterRepository->countTop100ForUser($user)['nb_top100_operating'],
            favourites: array_map(static fn (TopCoaster $topCoaster): Coaster => $topCoaster->getCoaster(), $favourites),
            mainTopId: [] === $favourites ? null : $favourites[0]->getTop()->getId(),
            tops: $this->topRepository->countForUser($user),
            records: $ridden >= self::RECORDS_MIN_RIDDEN ? $this->records($user) : [],
            ratings: $ridden >= self::RATINGS_MIN_RIDDEN ? $this->ratings($user) : null,
            reviews: $figures['reviews'],
            upvotes: $this->riddenCoasterRepository->sumReviewUpvotesForUser($user),
            photos: $images['photos'],
            likes: $images['likes'],
            years: $years['years'],
            undated: $ridden - $years['dated'],
        );
    }

    /** @return list<ProfileRecord> */
    private function records(User $user): array
    {
        $records = [];

        $extremes = [
            [ProfileRecord::TALLEST, 'height', self::WORLD_RECORD_HEIGHT, static fn (Coaster $c): ?int => $c->getHeight()],
            [ProfileRecord::FASTEST, 'speed', self::WORLD_RECORD_SPEED, static fn (Coaster $c): ?int => $c->getSpeed()],
            [ProfileRecord::LONGEST, 'length', self::WORLD_RECORD_LENGTH, static fn (Coaster $c): ?int => $c->getLength()],
            [ProfileRecord::INVERSIONS, 'inversionsNumber', self::WORLD_RECORD_INVERSIONS, static fn (Coaster $c): ?int => $c->getInversionsNumber()],
        ];
        foreach ($extremes as [$kind, $field, $worldRecord, $read]) {
            $coaster = $this->riddenCoasterRepository->findRiddenWithMaximum($user, $field);
            $value = null === $coaster ? 0 : (int) $read($coaster);
            if (null === $coaster || ($value <= 0 && ProfileRecord::INVERSIONS === $kind)) {
                continue;
            }

            $records[] = new ProfileRecord($kind, $value, $coaster, null, max(0, min(100, (int) round($value / $worldRecord * 100))));
        }

        $oldest = $this->riddenCoasterRepository->findOldestRidden($user, new \DateTimeImmutable(self::FIRST_OPENING_DATE));
        if (null !== $oldest && null !== $oldest->getOpeningDate()) {
            $records[] = new ProfileRecord(ProfileRecord::OLDEST, (int) $oldest->getOpeningDate()->format('Y'), $oldest);
        }

        $manufacturer = $this->riddenCoasterRepository->findMostRiddenManufacturer($user);
        if (null !== $manufacturer) {
            $records[] = new ProfileRecord(ProfileRecord::MANUFACTURER, $manufacturer['count'], null, $manufacturer['name']);
        }

        return $records;
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

        return new ProfileRatings(
            $counts,
            $total > 0 ? round($sum / $total, 1) : 0.0,
            $this->riddenCoasterRepository->findMostUsedTags($user, 'pros', self::TAGS),
            $this->riddenCoasterRepository->findMostUsedTags($user, 'cons', self::TAGS),
        );
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
