<?php

declare(strict_types=1);

namespace App\Tests\Service\Home;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;
use App\Service\Home\NextActionPicker;
use App\Tests\EntityId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NextActionPickerTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private TopRepository&MockObject $topRepository;
    private NextActionPicker $picker;
    private User $user;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->topRepository = $this->createMock(TopRepository::class);
        $this->picker = new NextActionPicker($this->riddenCoasterRepository, $this->topRepository);
        $this->user = new User();
        EntityId::set($this->user, 42);
    }

    /** A date whose day of the year puts $first at the head of the rotation (review, top, photo). */
    private static function dayStartingWith(string $first): \DateTimeImmutable
    {
        return new \DateTimeImmutable(['review' => '2026-01-01', 'top' => '2026-01-02', 'photo' => '2026-01-03'][$first].' 12:00:00');
    }

    private static function rating(Coaster $coaster, string $createdAt): RiddenCoaster
    {
        return new RiddenCoaster()->setCoaster($coaster)->setCreatedAt(new \DateTime($createdAt));
    }

    public function testAnEmptyAccountGetsNoAction(): void
    {
        $this->riddenCoasterRepository->expects($this->never())->method('findWithoutReview');

        $this->assertNull($this->picker->pick($this->user, 0, new \DateTimeImmutable()));
    }

    public function testARatingWithoutReviewFromTheLastDaysComesFirst(): void
    {
        $coaster = new Coaster();
        $now = self::dayStartingWith('photo');
        $this->riddenCoasterRepository->expects($this->once())->method('findWithoutReview')
            ->with($this->user, $now->modify('-14 days'), 4.5, 10)
            ->willReturn([self::rating($coaster, '2026-01-02 09:00:00')]);
        $this->riddenCoasterRepository->expects($this->never())->method('findWithFewPhotos');

        $this->assertSame(['type' => 'review', 'coaster' => $coaster], $this->picker->pick($this->user, 40, $now));
    }

    public function testAnOlderRatingWithoutReviewWaitsItsTurn(): void
    {
        $coaster = new Coaster();
        $this->riddenCoasterRepository->method('findWithoutReview')->willReturn([self::rating($coaster, '2025-12-20 09:00:00')]);
        $this->riddenCoasterRepository->method('findWithFewPhotos')->willReturn([['coaster' => new Coaster(), 'photos' => 1]]);

        $this->assertSame('photo', $this->picker->pick($this->user, 40, self::dayStartingWith('photo'))['type'] ?? null);
        $this->assertSame(['type' => 'review', 'coaster' => $coaster], $this->picker->pick($this->user, 40, self::dayStartingWith('review')));
    }

    public function testSuggestsATopFromTenRatingsWithoutAMainTop(): void
    {
        $this->topRepository->method('hasMainTop')->willReturn(false);

        $this->assertSame(['type' => 'top', 'ridden' => 10], $this->picker->pick($this->user, 10, self::dayStartingWith('top')));
    }

    public function testPutsAFavoriteForwardForTheMainTop(): void
    {
        $coaster = new Coaster();
        $this->riddenCoasterRepository->method('findMissingFromMainTop')->with($this->user, 4.5, 10)
            ->willReturn([['coaster' => $coaster, 'rating' => 5.0, 'top' => 3]]);

        $this->assertSame(
            ['type' => 'top_add', 'coaster' => $coaster, 'rating' => 5.0, 'top' => 3],
            $this->picker->pick($this->user, 40, self::dayStartingWith('top')),
        );
    }

    public function testAMainTopRulesOutTheTopSuggestion(): void
    {
        $this->topRepository->method('hasMainTop')->willReturn(true);

        $this->assertNull($this->picker->pick($this->user, 40, self::dayStartingWith('top')));
    }

    public function testSuggestsAPhotoForACoasterWithFewOfThem(): void
    {
        $coaster = new Coaster();
        $this->riddenCoasterRepository->method('findWithFewPhotos')->with($this->user, 3, 10)
            ->willReturn([['coaster' => $coaster, 'photos' => 2]]);

        $this->assertSame(
            ['type' => 'photo', 'coaster' => $coaster, 'photos' => 2],
            $this->picker->pick($this->user, 40, self::dayStartingWith('photo')),
        );
    }

    public function testGoesThroughEveryCandidateBeforeRepeatingOne(): void
    {
        $candidates = array_map(static fn (int $photos): array => ['coaster' => new Coaster(), 'photos' => $photos], range(0, 9));
        $this->riddenCoasterRepository->method('findWithFewPhotos')->willReturn($candidates);

        $day = self::dayStartingWith('photo');
        $this->assertSame($this->picker->pick($this->user, 40, $day), $this->picker->pick($this->user, 40, $day->modify('+8 hours')));

        // Photo leads one day in three.
        $named = [];
        for ($turn = 0; $turn < 10; ++$turn) {
            $named[] = $this->picker->pick($this->user, 40, $day->modify(\sprintf('+%d days', 3 * $turn)))['photos'] ?? null;
        }
        $this->assertEqualsCanonicalizing(range(0, 9), $named);
    }

    public function testAKindReachedByFallingThroughMovesOnEveryDay(): void
    {
        $candidates = array_map(static fn (int $photos): array => ['coaster' => new Coaster(), 'photos' => $photos], range(0, 9));
        $this->riddenCoasterRepository->method('findWithFewPhotos')->willReturn($candidates);

        $named = [];
        // Review and Top lead these days, but neither applies.
        foreach (['2026-01-01', '2026-01-02', '2026-01-04', '2026-01-05'] as $day) {
            $named[] = $this->picker->pick($this->user, 5, new \DateTimeImmutable($day.' 12:00:00'))['photos'] ?? null;
        }
        $this->assertCount(4, array_unique($named));
    }

    public function testFallsThroughToTheNextSuggestionThatApplies(): void
    {
        // Top comes first that day, but the member has too few ratings and no coaster short of photos.
        $coaster = new Coaster();
        $this->riddenCoasterRepository->method('findWithoutReview')->willReturn([self::rating($coaster, '2025-12-25 09:00:00')]);
        $this->riddenCoasterRepository->method('findWithFewPhotos')->willReturn([]);
        $this->topRepository->expects($this->never())->method('hasMainTop');

        $this->assertSame(['type' => 'review', 'coaster' => $coaster], $this->picker->pick($this->user, 9, self::dayStartingWith('top')));
    }
}
