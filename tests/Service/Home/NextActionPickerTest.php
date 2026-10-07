<?php

declare(strict_types=1);

namespace App\Tests\Service\Home;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;
use App\Service\Home\NextActionPicker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NextActionPickerTest extends TestCase
{
    private const array PARK = ['id' => 7, 'name' => 'Some Park', 'slug' => 'some-park', 'total' => 5, 'ridden' => 4];

    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private TopRepository&MockObject $topRepository;
    private ParkRepository&MockObject $parkRepository;
    private NextActionPicker $picker;
    private User $user;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->topRepository = $this->createMock(TopRepository::class);
        $this->parkRepository = $this->createMock(ParkRepository::class);
        $this->picker = new NextActionPicker($this->riddenCoasterRepository, $this->topRepository, $this->parkRepository);
        $this->user = new User();
    }

    /** A date whose day of the year puts $first at the head of the rotation (top, photo, park). */
    private static function dayStartingWith(string $first): \DateTimeImmutable
    {
        return new \DateTimeImmutable(['top' => '2026-01-01', 'photo' => '2026-01-02', 'park' => '2026-01-03'][$first]);
    }

    public function testAnEmptyAccountGetsNoAction(): void
    {
        $this->riddenCoasterRepository->expects($this->never())->method('findLatestWithoutReview');

        $this->assertNull($this->picker->pick($this->user, 0, new \DateTimeImmutable()));
    }

    public function testARecentRatingWithoutReviewComesFirst(): void
    {
        $coaster = new Coaster();
        $now = new \DateTimeImmutable('2026-10-07 12:00:00');
        $this->riddenCoasterRepository->expects($this->once())->method('findLatestWithoutReview')
            ->with($this->user, $now->modify('-14 days'))
            ->willReturn(new RiddenCoaster()->setCoaster($coaster));
        $this->topRepository->expects($this->never())->method('hasMainTop');

        $this->assertSame(['type' => 'review', 'coaster' => $coaster], $this->picker->pick($this->user, 40, $now));
    }

    public function testSuggestsATopFromTenRatingsWithoutAMainTop(): void
    {
        $this->topRepository->method('hasMainTop')->willReturn(false);

        $this->assertSame(['type' => 'top', 'ridden' => 10], $this->picker->pick($this->user, 10, self::dayStartingWith('top')));
    }

    public function testSuggestsAPhotoForACoasterWithFewOfThem(): void
    {
        $coaster = new Coaster();
        $this->riddenCoasterRepository->method('findLatestWithFewPhotos')->with($this->user, 3)
            ->willReturn(['coaster' => $coaster, 'photos' => 2]);

        $this->assertSame(
            ['type' => 'photo', 'coaster' => $coaster, 'photos' => 2],
            $this->picker->pick($this->user, 40, self::dayStartingWith('photo')),
        );
    }

    public function testSuggestsTheParkClosestToCompletion(): void
    {
        $this->parkRepository->method('findClosestToCompletion')->willReturn(self::PARK);

        $this->assertSame(['type' => 'park', 'park' => self::PARK], $this->picker->pick($this->user, 40, self::dayStartingWith('park')));
    }

    public function testFallsThroughToTheNextSuggestionThatApplies(): void
    {
        // Top comes first that day, but the member has too few ratings and no coaster short of photos.
        $this->riddenCoasterRepository->method('findLatestWithFewPhotos')->willReturn(null);
        $this->parkRepository->method('findClosestToCompletion')->willReturn(self::PARK);
        $this->topRepository->expects($this->never())->method('hasMainTop');

        $this->assertSame('park', $this->picker->pick($this->user, 9, self::dayStartingWith('top'))['type'] ?? null);
    }

    public function testAMainTopRulesOutTheTopSuggestion(): void
    {
        $this->topRepository->method('hasMainTop')->willReturn(true);

        $this->assertNull($this->picker->pick($this->user, 40, self::dayStartingWith('top')));
    }
}
