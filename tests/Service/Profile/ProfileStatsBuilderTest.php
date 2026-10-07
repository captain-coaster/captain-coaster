<?php

declare(strict_types=1);

namespace App\Tests\Service\Profile;

use App\DTO\Profile\ProfileRecord;
use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\RiddenCoaster;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;
use App\Service\Profile\ProfileStatsBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProfileStatsBuilderTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private CoasterRepository&MockObject $coasterRepository;
    private TopRepository&MockObject $topRepository;
    private ImageRepository&MockObject $imageRepository;
    private ProfileStatsBuilder $builder;
    private User $user;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->topRepository = $this->createMock(TopRepository::class);
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->builder = new ProfileStatsBuilder(
            $this->riddenCoasterRepository,
            $this->coasterRepository,
            $this->topRepository,
            $this->imageRepository,
        );
        $this->user = new User();
        $this->now = new \DateTimeImmutable('2026-10-07 12:00:00');

        $this->imageRepository->method('countPhotosAndLikesForUser')->willReturn(['photos' => 620, 'likes' => 1392]);
        $this->riddenCoasterRepository->method('countTop100ForUser')->willReturn(['nb_top100' => 80, 'nb_top100_operating' => 79]);
        $this->topRepository->method('countForUser')->willReturn(4);
    }

    /** @param array{ridden?: int, riddenInYear?: int, reviews?: int, upvotes?: int, parks?: int, countries?: int} $figures */
    private function ridden(int $ridden, array $figures = []): void
    {
        $this->riddenCoasterRepository->method('countProfileFiguresForUser')
            ->willReturn($figures + ['ridden' => $ridden, 'riddenInYear' => 5, 'reviews' => 14, 'upvotes' => 33, 'parks' => 198, 'countries' => 21]);
    }

    /**
     * @param array{height?: ?int, speed?: ?int, length?: ?int, inversions?: ?int, openingDate?: ?\DateTimeInterface, worldRank?: ?int} $fact
     *
     * @return array{id: int, height: ?int, speed: ?int, length: ?int, inversions: ?int, openingDate: ?\DateTimeInterface, worldRank: ?int}
     */
    private static function fact(int $id, array $fact = []): array
    {
        return $fact + ['id' => $id, 'height' => null, 'speed' => null, 'length' => null, 'inversions' => null, 'openingDate' => null, 'worldRank' => null];
    }

    /** @param list<array{string, int}> $rides date, count */
    private function rides(array $rides): void
    {
        $this->riddenCoasterRepository->method('countRidesByDate')->willReturn(
            array_map(static fn (array $ride): array => ['date' => new \DateTimeImmutable($ride[0]), 'count' => $ride[1]], $rides)
        );
    }

    public function testAnEmptyAccountRunsNoOtherQuery(): void
    {
        $this->riddenCoasterRepository->method('countProfileFiguresForUser')->willReturn(['ridden' => 0, 'riddenInYear' => 0, 'reviews' => 0, 'upvotes' => 0, 'parks' => 0, 'countries' => 0]);
        $this->topRepository->expects($this->never())->method('findMainTopHead');
        $this->riddenCoasterRepository->expects($this->never())->method('countRidesByDate');

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame(0, $stats->ridden);
        $this->assertSame(0, $stats->parks);
        $this->assertSame(0, $stats->top100);
        $this->assertSame(2026, $stats->year);
        $this->assertSame([], $stats->favourites);
        $this->assertNull($stats->mainTopId);
        $this->assertSame([], $stats->records);
        $this->assertNull($stats->ratings);
        $this->assertSame([], $stats->years);
        $this->assertSame(0, $stats->undated);
    }

    public function testFiguresAreCarriedOver(): void
    {
        $this->ridden(50);
        $this->rides([]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame(50, $stats->ridden);
        $this->assertSame(198, $stats->parks);
        $this->assertSame(21, $stats->countries);
        $this->assertSame(5, $stats->riddenInYear);
        $this->assertSame(79, $stats->top100);
        $this->assertSame(4, $stats->tops);
        $this->assertSame(14, $stats->reviews);
        $this->assertSame(33, $stats->upvotes);
        $this->assertSame(620, $stats->photos);
        $this->assertSame(1392, $stats->likes);
        $this->assertTrue($stats->hasContributions());
        $this->assertSame(50, $stats->undated);
    }

    public function testFavouritesAreTheCoastersOfTheMainTop(): void
    {
        $this->ridden(50);
        $this->rides([]);
        $top = new Top();
        new \ReflectionProperty(Top::class, 'id')->setValue($top, 12);
        $first = new Coaster();
        $second = new Coaster();
        $this->topRepository->method('findMainTopHead')->with($this->user, 3)->willReturn([
            new TopCoaster()->setTop($top)->setCoaster($first)->setPosition(1),
            new TopCoaster()->setTop($top)->setCoaster($second)->setPosition(2),
        ]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame([$first, $second], $stats->favourites);
        $this->assertSame(12, $stats->mainTopId);
    }

    public function testNoMainTopMeansNoTopId(): void
    {
        $this->ridden(50);
        $this->rides([]);
        $this->topRepository->method('findMainTopHead')->willReturn([]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame([], $stats->favourites);
        $this->assertNull($stats->mainTopId);
    }

    public function testRecordsNeedTenCoasters(): void
    {
        $this->ridden(9);
        $this->rides([]);
        $this->riddenCoasterRepository->expects($this->never())->method('findRiddenCoasterFacts');

        $this->assertSame([], $this->builder->build($this->user, $this->now)->records);
    }

    public function testRecordsAtTenCoasters(): void
    {
        $this->ridden(10);
        $this->rides([]);
        $this->riddenCoasterRepository->method('findRiddenCoasterFacts')->willReturn([
            self::fact(1, ['height' => 139, 'speed' => 206, 'length' => 1000, 'inversions' => 0, 'openingDate' => new \DateTime('2010-05-01')]),
            self::fact(2, ['height' => 60, 'speed' => 90, 'length' => 800, 'inversions' => 0, 'openingDate' => new \DateTime('1927-06-01')]),
            // A placeholder opening date is not the oldest.
            self::fact(3, ['height' => 20, 'openingDate' => new \DateTime('0001-01-01')]),
        ]);
        $tallest = new Coaster();
        $oldest = new Coaster();
        $this->coasterRepository->expects($this->once())->method('findWithParkAndImage')->with([1, 2])->willReturn([1 => $tallest, 2 => $oldest]);

        $records = $this->builder->build($this->user, $this->now)->records;

        $this->assertSame(
            [ProfileRecord::TALLEST, ProfileRecord::FASTEST, ProfileRecord::LONGEST, ProfileRecord::OLDEST],
            array_map(static fn (ProfileRecord $record): string => $record->kind, $records),
            'inversions are skipped at 0'
        );
        $this->assertSame([139, 206, 1000, 1927], array_map(static fn (ProfileRecord $record): int => $record->value, $records));
        $this->assertSame($tallest, $records[0]->coaster);
        $this->assertSame($oldest, $records[3]->coaster);
    }

    public function testRecordTiesGoToTheBestRankedThenTheLowestId(): void
    {
        $this->ridden(10);
        $this->rides([]);
        $this->riddenCoasterRepository->method('findRiddenCoasterFacts')->willReturn([
            self::fact(9, ['height' => 100, 'inversions' => 7]),
            self::fact(5, ['height' => 100, 'inversions' => 7, 'worldRank' => 40]),
            self::fact(4, ['height' => 100, 'inversions' => 7, 'worldRank' => 12]),
            self::fact(3, ['inversions' => 7]),
        ]);
        $this->coasterRepository->method('findWithParkAndImage')->with([4])->willReturn([4 => new Coaster()]);

        $records = $this->builder->build($this->user, $this->now)->records;

        $this->assertSame([ProfileRecord::TALLEST, ProfileRecord::INVERSIONS], array_map(static fn (ProfileRecord $record): string => $record->kind, $records));
        $this->assertSame([100, 7], array_map(static fn (ProfileRecord $record): int => $record->value, $records));
    }

    public function testRatingsNeedTwentyCoasters(): void
    {
        $this->ridden(19);
        $this->rides([]);
        $this->riddenCoasterRepository->expects($this->never())->method('countRatingsByValue');

        $this->assertNull($this->builder->build($this->user, $this->now)->ratings);
    }

    public function testRatingsAtTwentyCoasters(): void
    {
        $this->ridden(20);
        $this->rides([]);
        $this->riddenCoasterRepository->method('countRatingsByValue')->willReturn([
            ['value' => 5.0, 'count' => 6],
            ['value' => 0.5, 'count' => 2],
            ['value' => 3.5, 'count' => 12],
        ]);

        $ratings = $this->builder->build($this->user, $this->now)->ratings;

        $this->assertNotNull($ratings);
        $this->assertSame([2, 0, 0, 0, 0, 0, 12, 0, 0, 6], $ratings->counts);
        $this->assertSame(12, $ratings->max());
        // (1 + 42 + 30) / 20 = 3.65
        $this->assertSame(3.7, $ratings->average);
    }

    public function testAFeaturedReviewSitsBesideOnePhoto(): void
    {
        $this->ridden(3);
        $this->rides([]);
        $review = new RiddenCoaster();
        $photo = new Image();
        $this->riddenCoasterRepository->method('findMostUpvotedReview')->with($this->user, 300)->willReturn($review);
        $this->imageRepository->expects($this->once())->method('findMostLikedForUser')->with($this->user, 1)->willReturn([$photo]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame($review, $stats->topReview);
        $this->assertSame([$photo], $stats->topPhotos);
    }

    public function testWithoutAFeaturedReviewTwoPhotosShow(): void
    {
        $this->ridden(3);
        $this->rides([]);
        $photos = [new Image(), new Image()];
        $this->riddenCoasterRepository->method('findMostUpvotedReview')->willReturn(null);
        $this->imageRepository->expects($this->once())->method('findMostLikedForUser')->with($this->user, 2)->willReturn($photos);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertNull($stats->topReview);
        $this->assertSame($photos, $stats->topPhotos);
    }

    public function testYearsFillGapsAndIgnoreOutOfRangeDates(): void
    {
        $this->ridden(30);
        $this->rides([
            ['0007-05-21', 8],
            ['1949-12-31', 1],
            ['2027-01-01', 2],
            ['2022-03-01', 4],
            ['2022-08-01', 1],
            ['2024-05-05', 3],
            ['2026-10-07', 2],
        ]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame([
            ['year' => 2022, 'count' => 5],
            ['year' => 2023, 'count' => 0],
            ['year' => 2024, 'count' => 3],
            ['year' => 2025, 'count' => 0],
            ['year' => 2026, 'count' => 2],
        ], $stats->years);
        $this->assertSame(30 - 10, $stats->undated);
    }

    public function testYearsHaveNoTrailingZero(): void
    {
        $this->ridden(10);
        $this->rides([['2023-04-01', 3], ['2024-04-01', 1]]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame([['year' => 2023, 'count' => 3], ['year' => 2024, 'count' => 1]], $stats->years);
        $this->assertSame(6, $stats->undated);
    }

    public function testYearsAreCappedAtTheTenMostRecent(): void
    {
        $this->ridden(40);
        $this->rides(array_map(static fn (int $year): array => [\sprintf('%d-06-01', $year), 1], range(2012, 2026)));

        $years = $this->builder->build($this->user, $this->now)->years;

        $this->assertCount(10, $years);
        $this->assertSame(2017, $years[0]['year']);
        $this->assertSame(2026, $years[9]['year']);
    }

    public function testYearsFarApartKeepTheLatestTen(): void
    {
        $this->ridden(10);
        $this->rides([['2005-06-01', 1], ['2026-06-01', 2]]);

        $years = $this->builder->build($this->user, $this->now)->years;

        $this->assertCount(10, $years);
        $this->assertSame(2017, $years[0]['year']);
        $this->assertSame(['year' => 2026, 'count' => 2], $years[9]);
    }

    public function testNoDatedRideMeansNoYears(): void
    {
        $this->ridden(10);
        $this->rides([]);

        $stats = $this->builder->build($this->user, $this->now);

        $this->assertSame([], $stats->years);
        $this->assertSame(10, $stats->undated);
    }
}
