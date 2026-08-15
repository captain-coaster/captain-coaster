<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Repository\CoasterRepository;
use App\Repository\ContinentRepository;

/**
 * Provides world-record style data for the Records page:
 * top 3 coasters by height, length, speed, and inversions.
 *
 * Only considers operating and temporarily closed coasters.
 * Optionally filters by a continent slug (null = World).
 */
class RecordsService
{
    /**
     * Safety cap on how many rows a category can show when ties push the row
     * count above the podium size (e.g. two coasters tied for #1 pushes a
     * would-be #3 out to #4, but a long run of ties is still bounded).
     */
    private const int TIE_FETCH_CAP = 5;

    /** Categories shown on the Records page (label key, metric, icon, unit-formatter). */
    public const array CATEGORIES = [
        [
            'key' => 'tallest',
            'metric' => 'height',
            'icon' => 'tabler:arrow-up',
            'unit' => 'm_or_f',
        ],
        [
            'key' => 'longest',
            'metric' => 'length',
            'icon' => 'tabler:ruler-measure',
            'unit' => 'm_or_f',
        ],
        [
            'key' => 'fastest',
            'metric' => 'speed',
            'icon' => 'tabler:bolt',
            'unit' => 'kph_or_mph',
        ],
        [
            'key' => 'most_inversions',
            'metric' => 'inversionsNumber',
            'icon' => 'tabler:rotate-clockwise',
            'unit' => null,
        ],
    ];

    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly ContinentRepository $continentRepository,
    ) {
    }

    /**
     * Build the data structure consumed by the Records template.
     *
     * @return array<int, array{
     *     key: string,
     *     metric: string,
     *     icon: string,
     *     unit: string|null,
     *     coasters: array<int, array{coaster: Coaster, rank: int}>
     * }>
     */
    public function getRecords(?string $continentSlug = null, int $limit = 3): array
    {
        $records = [];

        foreach (self::CATEGORIES as $category) {
            $topCoasters = $this->coasterRepository->findTopByMetric(
                $category['metric'],
                self::TIE_FETCH_CAP,
                $continentSlug,
            );

            $records[] = [
                'key' => $category['key'],
                'metric' => $category['metric'],
                'icon' => $category['icon'],
                'unit' => $category['unit'],
                'coasters' => $this->rankWithTies($topCoasters, $category['metric'], $limit),
            ];
        }

        return $records;
    }

    /**
     * Assigns standard competition ranking (ties share a rank, e.g. 1,2,2,4) to a
     * metric-DESC-ordered list of coasters, then trims to rows ranked within $limit.
     *
     * @param array<int, Coaster> $coasters metric-DESC ordered (ties broken by id)
     *
     * @return array<int, array{coaster: Coaster, rank: int}>
     */
    private function rankWithTies(array $coasters, string $metric, int $limit): array
    {
        $ranked = [];
        $previousValue = null;
        $rank = 0;

        foreach ($coasters as $position => $coaster) {
            $value = match ($metric) {
                'height' => $coaster->getHeight(),
                'length' => $coaster->getLength(),
                'speed' => $coaster->getSpeed(),
                'inversionsNumber' => $coaster->getInversionsNumber(),
                default => throw new \InvalidArgumentException(\sprintf('Invalid metric "%s".', $metric)),
            };

            if (null === $previousValue || $value !== $previousValue) {
                $rank = $position + 1;
            }

            if ($rank > $limit) {
                break;
            }

            $ranked[] = ['coaster' => $coaster, 'rank' => $rank];
            $previousValue = $value;
        }

        return $ranked;
    }

    /**
     * Continent pills for the filter row.
     * The first pill is always "World" (slug = null).
     *
     * @return array<int, array{slug: string|null, name: string}>
     */
    public function getContinentPills(): array
    {
        $pills = [['slug' => null, 'name' => 'continent.world']];

        foreach ($this->continentRepository->findBy([], ['name' => 'ASC']) as $continent) {
            $pills[] = [
                'slug' => $continent->getSlug(),
                'name' => $continent->getName(),
            ];
        }

        return $pills;
    }
}
