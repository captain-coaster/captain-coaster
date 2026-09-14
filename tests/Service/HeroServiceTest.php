<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Service\HeroService;
use Doctrine\ORM\NoResultException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HeroServiceTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private ImageRepository&MockObject $imageRepository;
    private HeroService $service;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->service = new HeroService($this->coasterRepository, $this->imageRepository);
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
        $coaster = $this->createMock(Coaster::class);
        $this->coasterRepository->method('findUpcomingCoaster')->willReturn($coaster);
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willThrowException(new NoResultException());

        $this->assertSame(['type' => 'upcoming', 'coaster' => $coaster], $this->service->pick());
    }

    public function testFeaturedImageCandidateIsIncludedWhenAvailable(): void
    {
        $image = $this->createMock(Image::class);
        $this->coasterRepository->method('findUpcomingCoaster')->willReturn(null);
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn(null);
        $this->imageRepository->method('findFeaturedImage')->willReturn($image);

        $this->assertSame(['type' => 'photo', 'image' => $image], $this->service->pick());
    }

    public function testPicksAmongAllAvailableCandidates(): void
    {
        $this->coasterRepository->method('findUpcomingCoaster')->willReturn($this->createMock(Coaster::class));
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn($this->createMock(Coaster::class));
        $this->coasterRepository->method('findTrendingCoaster')->willReturn($this->createMock(Coaster::class));
        $this->imageRepository->method('findFeaturedImage')->willReturn($this->createMock(Image::class));

        // (3/4)^50 chance of missing a type by fluke is ~1e-6 -- negligible flake risk.
        $seenTypes = [];
        for ($i = 0; $i < 50; ++$i) {
            $seenTypes[$this->service->pick()['type']] = true;
        }

        $this->assertEqualsCanonicalizing(['upcoming', 'new', 'trending', 'photo'], array_keys($seenTypes));
    }
}
