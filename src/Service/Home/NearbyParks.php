<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Entity\Image;
use App\Entity\User;
use App\Repository\ParkRepository;

/**
 * The parks nearest to a position, with a member's progress in each. Distances are measured here, over the
 * cached list of park coordinates, so the lookup needs no geographic SQL.
 */
class NearbyParks
{
    /** Closer than this, the visitor is taken to be at the park. A starting value, to calibrate in the field. */
    public const float HERE_KM = 2.0;

    private const float EARTH_RADIUS_KM = 6371.0;

    public function __construct(private readonly ParkRepository $parkRepository)
    {
    }

    /** @return list<array{id: int, name: string, slug: string, total: int, ridden: int, distance: float, here: bool, image: ?Image}> nearest first */
    public function nearest(float $latitude, float $longitude, ?User $user, int $limit = 5): array
    {
        $distances = [];
        foreach ($this->parkRepository->findCoordinates() as $park) {
            $distances[$park['id']] = self::distanceKm($latitude, $longitude, $park['latitude'], $park['longitude']);
        }
        asort($distances);
        $distances = \array_slice($distances, 0, $limit, true);

        $ids = array_keys($distances);
        $progress = $this->parkRepository->findProgress($ids, $user);
        $covers = $this->parkRepository->findCovers($ids);

        $parks = [];
        foreach ($distances as $id => $distance) {
            if (!isset($progress[$id])) {
                continue;
            }

            $parks[] = $progress[$id] + [
                'distance' => $distance,
                'here' => $distance <= self::HERE_KM,
                'image' => $covers[$id] ?? null,
            ];
        }

        return $parks;
    }

    /** Great-circle distance (haversine). */
    public static function distanceKm(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitude = deg2rad($toLatitude - $fromLatitude);
        $longitude = deg2rad($toLongitude - $fromLongitude);
        $a = sin($latitude / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitude / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
