<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\Park;
use App\Entity\Status;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Service\HeroService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class HeroServiceTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private ImageRepository&MockObject $imageRepository;
    private HeroService $service;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->service = new HeroService($this->coasterRepository, $this->imageRepository, new ArrayAdapter());
    }

    private function makeCoaster(int $id = 1, string $slug = 'some-coaster'): Coaster&MockObject
    {
        $park = $this->createMock(Park::class);
        $park->method('getName')->willReturn('Some Park');
        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('status.operating');

        $coaster = $this->createMock(Coaster::class);
        $coaster->method('getId')->willReturn($id);
        $coaster->method('getSlug')->willReturn($slug);
        $coaster->method('getName')->willReturn('Some Coaster');
        $coaster->method('getPark')->willReturn($park);
        $coaster->method('getStatus')->willReturn($status);
        $coaster->method('getMainImage')->willReturn($this->makeImage());

        return $coaster;
    }

    private function makeImage(): Image&MockObject
    {
        $image = $this->createMock(Image::class);
        $image->method('getFilename')->willReturn('some-image.jpg');
        $image->method('getCredit')->willReturn('Some Credit');

        return $image;
    }

    /** @param array<string, list<int>> $ids category => candidate ids; omitted categories are empty */
    private function stubIds(array $ids): void
    {
        $this->coasterRepository->method('findUpcomingCoasterIds')->willReturn($ids['upcoming'] ?? []);
        $this->coasterRepository->method('findRecentlyOpenedCoasterIds')->willReturn($ids['new'] ?? []);
        $this->coasterRepository->method('findTrendingCoasterIds')->willReturn($ids['trending'] ?? []);
        $this->imageRepository->method('findFeaturedImageIds')->willReturn($ids['photo'] ?? []);
    }

    public function testReturnsNullWhenNoCategoryHasACandidate(): void
    {
        $this->stubIds([]);

        $this->assertNull($this->service->pick());
    }

    public function testReturnsTheOnlyCandidateWhenJustOneExists(): void
    {
        $this->stubIds(['upcoming' => [1]]);
        $this->coasterRepository->method('find')->with(1)->willReturn($this->makeCoaster());

        $pick = $this->service->pick();

        $this->assertSame('upcoming', $pick['type']);
        $this->assertSame(1, $pick['coasterId']);
        $this->assertSame('some-coaster', $pick['coasterSlug']);
        $this->assertSame('Some Coaster', $pick['coasterName']);
        $this->assertSame('Some Park', $pick['parkName']);
        $this->assertSame('some-image.jpg', $pick['imageFilename']);
        $this->assertSame('Some Credit', $pick['imageCredit']);
        $this->assertSame('status.operating', $pick['statusName']);
    }

    public function testSkipsEmptyCategories(): void
    {
        $this->stubIds(['trending' => [4]]);
        $this->coasterRepository->method('find')->with(4)->willReturn($this->makeCoaster(4));

        // Whatever the shuffled order, the only category with candidates must win.
        for ($i = 0; $i < 20; ++$i) {
            $this->service->invalidate();
            $this->assertSame('trending', $this->service->pick()['type']);
        }
    }

    public function testPhotoCandidateResolvesItsCoaster(): void
    {
        $image = $this->makeImage();
        $image->method('getCoaster')->willReturn($this->makeCoaster());

        $this->stubIds(['photo' => [7]]);
        $this->imageRepository->method('find')->with(7)->willReturn($image);

        $pick = $this->service->pick();

        $this->assertSame('photo', $pick['type']);
        $this->assertSame('Some Coaster', $pick['coasterName']);
        $this->assertSame('some-image.jpg', $pick['imageFilename']);
    }

    public function testCoasterCandidateWithoutAMainImageYieldsNothing(): void
    {
        $coasterWithoutImage = $this->createMock(Coaster::class);
        $coasterWithoutImage->method('getMainImage')->willReturn(null);

        $this->stubIds(['upcoming' => [1]]);
        $this->coasterRepository->method('find')->willReturn($coasterWithoutImage);

        $this->assertNull($this->service->pick());
    }

    public function testFallsBackToAnotherCategoryWhenACandidateNoLongerLoads(): void
    {
        $this->stubIds(['upcoming' => [1], 'new' => [2]]);
        $this->coasterRepository->method('find')->willReturnCallback(
            fn (int $id) => 2 === $id ? $this->makeCoaster(2) : null
        );

        for ($i = 0; $i < 20; ++$i) {
            $this->service->invalidate();
            $this->assertSame('new', $this->service->pick()['type']);
        }
    }

    public function testPickIsCachedAcrossCalls(): void
    {
        $this->stubIds(['upcoming' => [1]]);
        $this->coasterRepository->expects($this->once())->method('find')->willReturn($this->makeCoaster());

        $first = $this->service->pick();
        $second = $this->service->pick();

        $this->assertSame($first, $second);
    }

    public function testInvalidateForcesRecomputeOnNextPick(): void
    {
        $this->stubIds(['upcoming' => [1]]);
        $this->coasterRepository->expects($this->exactly(2))->method('find')->willReturn($this->makeCoaster());

        $this->service->pick();
        $this->service->invalidate();
        $this->service->pick();
    }

    public function testEveryCategoryIsReachable(): void
    {
        $image = $this->makeImage();
        $image->method('getCoaster')->willReturn($this->makeCoaster());

        $this->stubIds(['upcoming' => [1], 'new' => [1], 'trending' => [1], 'photo' => [1]]);
        $this->coasterRepository->method('find')->willReturn($this->makeCoaster());
        $this->imageRepository->method('find')->willReturn($image);

        // (3/4)^50 chance of missing a type by fluke is ~1e-6 -- negligible flake risk.
        // Each iteration invalidates first since pick() is otherwise cached for an hour.
        $seenTypes = [];
        for ($i = 0; $i < 50; ++$i) {
            $this->service->invalidate();
            $seenTypes[$this->service->pick()['type']] = true;
        }

        $this->assertEqualsCanonicalizing(['upcoming', 'new', 'trending', 'photo'], array_keys($seenTypes));
    }

    public function testEveryCandidateOfACategoryIsReachable(): void
    {
        $this->stubIds(['upcoming' => [1, 2]]);
        $this->coasterRepository->method('find')->willReturnCallback(
            fn (int $id) => $this->makeCoaster($id, "coaster-{$id}")
        );

        // (1/2)^50 chance of missing one of the two by fluke is negligible.
        $seenSlugs = [];
        for ($i = 0; $i < 50; ++$i) {
            $this->service->invalidate();
            $seenSlugs[$this->service->pick()['coasterSlug']] = true;
        }

        $this->assertEqualsCanonicalizing(['coaster-1', 'coaster-2'], array_keys($seenSlugs));
    }
}
