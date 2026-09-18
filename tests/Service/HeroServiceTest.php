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
use Doctrine\ORM\NoResultException;
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

    private function makeCoaster(): Coaster&MockObject
    {
        $park = $this->createMock(Park::class);
        $park->method('getName')->willReturn('Some Park');
        $status = $this->createMock(Status::class);
        $status->method('getName')->willReturn('status.operating');

        $coaster = $this->createMock(Coaster::class);
        $coaster->method('getId')->willReturn(1);
        $coaster->method('getSlug')->willReturn('some-coaster');
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

    public function testReturnsNullWhenNoCandidateExists(): void
    {
        $this->coasterRepository->method('findUpcomingCoaster')->willReturn(null);
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willThrowException(new NoResultException());

        $this->assertNull($this->service->pick());
    }

    public function testReturnsTheOnlyCandidateWhenJustOneExists(): void
    {
        $coaster = $this->makeCoaster();
        $this->coasterRepository->method('findUpcomingCoaster')->willReturn($coaster);
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willThrowException(new NoResultException());

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

    public function testFeaturedImageCandidateResolvesItsCoaster(): void
    {
        $coaster = $this->makeCoaster();
        $image = $this->makeImage();
        $image->method('getCoaster')->willReturn($coaster);

        $this->coasterRepository->method('findUpcomingCoaster')->willReturn(null);
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willReturn($image);

        $pick = $this->service->pick();

        $this->assertSame('photo', $pick['type']);
        $this->assertSame('Some Coaster', $pick['coasterName']);
        $this->assertSame('some-image.jpg', $pick['imageFilename']);
    }

    public function testSkipsCoasterCandidateWithoutAMainImage(): void
    {
        $coasterWithoutImage = $this->createMock(Coaster::class);
        $coasterWithoutImage->method('getMainImage')->willReturn(null);

        $this->coasterRepository->method('findUpcomingCoaster')->willReturn($coasterWithoutImage);
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willThrowException(new NoResultException());

        $this->assertNull($this->service->pick());
    }

    public function testPickIsCachedAcrossCalls(): void
    {
        $this->coasterRepository->expects($this->once())->method('findUpcomingCoaster')->willReturn($this->makeCoaster());
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willThrowException(new NoResultException());

        $first = $this->service->pick();
        $second = $this->service->pick();

        $this->assertSame($first, $second);
    }

    public function testInvalidateForcesRecomputeOnNextPick(): void
    {
        $this->coasterRepository->expects($this->exactly(2))->method('findUpcomingCoaster')->willReturn($this->makeCoaster());
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willThrowException(new NoResultException());

        $this->service->pick();
        $this->service->invalidate();
        $this->service->pick();
    }

    public function testPicksAmongAllAvailableCandidates(): void
    {
        $this->coasterRepository->method('findUpcomingCoaster')->willReturn($this->makeCoaster());
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn($this->makeCoaster());
        $this->coasterRepository->method('findTrendingCoaster')->willReturn($this->makeCoaster());
        $this->imageRepository->method('findFeaturedImage')->willReturn($this->makeImage());

        // (3/4)^50 chance of missing a type by fluke is ~1e-6 -- negligible flake risk.
        // Each iteration invalidates first since pick() is otherwise cached for an hour.
        $seenTypes = [];
        for ($i = 0; $i < 50; ++$i) {
            $this->service->invalidate();
            $seenTypes[$this->service->pick()['type']] = true;
        }

        $this->assertEqualsCanonicalizing(['upcoming', 'new', 'trending', 'photo'], array_keys($seenTypes));
    }
}
