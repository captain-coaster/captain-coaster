<?php

declare(strict_types=1);

namespace App\Tests\Service\Home;

use App\Entity\Image;
use App\Repository\ParkRepository;
use App\Service\Home\NearbyParks;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NearbyParksTest extends TestCase
{
    // Europa-Park's entrance, and parks at growing distances from it.
    private const float LATITUDE = 48.2661;
    private const float LONGITUDE = 7.7220;
    private const array COORDINATES = [
        ['id' => 3, 'latitude' => 47.9034, 'longitude' => 7.9480],
        ['id' => 1, 'latitude' => 48.2685, 'longitude' => 7.7215],
        ['id' => 2, 'latitude' => 48.2840, 'longitude' => 7.7620],
    ];

    private ParkRepository&MockObject $parkRepository;
    private NearbyParks $nearbyParks;

    protected function setUp(): void
    {
        $this->parkRepository = $this->createMock(ParkRepository::class);
        $this->parkRepository->method('findCoordinates')->willReturn(self::COORDINATES);
        $this->nearbyParks = new NearbyParks($this->parkRepository);
    }

    /** @return array{id: int, name: string, slug: string, total: int, ridden: int} */
    private static function progress(int $id): array
    {
        return ['id' => $id, 'name' => "Park {$id}", 'slug' => "park-{$id}", 'total' => 4, 'ridden' => 1];
    }

    public function testDistanceIsTheGreatCircleDistance(): void
    {
        // Paris to London, about 344 km.
        $this->assertEqualsWithDelta(344, NearbyParks::distanceKm(48.8566, 2.3522, 51.5074, -0.1278), 1);
        $this->assertSame(0.0, NearbyParks::distanceKm(48.0, 7.0, 48.0, 7.0));
    }

    public function testReturnsTheNearestParksFirstWithTheirProgressAndCover(): void
    {
        $cover = $this->createStub(Image::class);
        $this->parkRepository->expects($this->once())->method('findProgress')->with([1, 2], null)
            ->willReturn([1 => self::progress(1), 2 => self::progress(2)]);
        $this->parkRepository->method('findCovers')->willReturn([2 => $cover]);

        $parks = $this->nearbyParks->nearest(self::LATITUDE, self::LONGITUDE, null, 2);

        $this->assertSame([1, 2], array_column($parks, 'id'));
        $this->assertSame('Park 1', $parks[0]['name']);
        $this->assertNull($parks[0]['image']);
        $this->assertSame($cover, $parks[1]['image']);
        $this->assertLessThan($parks[1]['distance'], $parks[0]['distance']);
    }

    public function testOnlyAParkWithinReachCountsAsHere(): void
    {
        $this->parkRepository->method('findProgress')->willReturn([1 => self::progress(1), 2 => self::progress(2)]);

        $parks = $this->nearbyParks->nearest(self::LATITUDE, self::LONGITUDE, null, 2);

        $this->assertTrue($parks[0]['here']);
        $this->assertFalse($parks[1]['here']);
    }

    public function testSkipsAParkWithoutProgressRow(): void
    {
        $this->parkRepository->method('findProgress')->willReturn([2 => self::progress(2)]);

        $this->assertSame([2], array_column($this->nearbyParks->nearest(self::LATITUDE, self::LONGITUDE, null, 2), 'id'));
    }
}
