<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Picks the homepage hero: a random category among
 *   - upcoming : coasters under construction or announced
 *   - new      : coasters opened in the last 90 days
 *   - trending : coasters with a lot of recent ride activity
 *   - photo    : heavily-liked, moderated user photos
 * then a random candidate in it, skipping categories with no candidate.
 *
 * Only the resolved pick is cached (an hour, under CACHE_KEY), so a warm homepage load
 * touches neither the DB nor a Doctrine lazy-load. ImageListener calls invalidate() on any
 * image enable/disable/delete so a moderation action takes effect immediately.
 */
class HeroService
{
    private const string CACHE_KEY = 'hero_pick';

    private const array CATEGORIES = ['upcoming', 'new', 'trending', 'photo'];

    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly ImageRepository $imageRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return array{type: string, coasterId: ?int, coasterSlug: ?string, coasterName: string, parkName: ?string, imageFilename: string, imageCredit: ?string, statusName: ?string}|null */
    public function pick(): ?array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): ?array {
            $item->expiresAfter(3600);

            return $this->resolve();
        });
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /** @return array{type: string, coasterId: ?int, coasterSlug: ?string, coasterName: string, parkName: ?string, imageFilename: string, imageCredit: ?string, statusName: ?string}|null */
    private function resolve(): ?array
    {
        $categories = self::CATEGORIES;
        shuffle($categories);

        foreach ($categories as $type) {
            $ids = $this->candidateIds($type);
            if ([] === $ids) {
                continue;
            }

            $pick = $this->load($type, $ids[array_rand($ids)]);
            if (null !== $pick) {
                return $pick;
            }
        }

        return null;
    }

    /** @return array<int> */
    private function candidateIds(string $type): array
    {
        return match ($type) {
            'upcoming' => $this->coasterRepository->findUpcomingCoasterIds(),
            'new' => $this->coasterRepository->findRecentlyOpenedCoasterIds(),
            'trending' => $this->coasterRepository->findTrendingCoasterIds(),
            default => $this->imageRepository->findFeaturedImageIds(),
        };
    }

    /** @return array{type: string, coasterId: ?int, coasterSlug: ?string, coasterName: string, parkName: ?string, imageFilename: string, imageCredit: ?string, statusName: ?string}|null */
    private function load(string $type, int $id): ?array
    {
        if ('photo' === $type) {
            $image = $this->imageRepository->find($id);
            $coaster = $image?->getCoaster();
        } else {
            $coaster = $this->coasterRepository->find($id);
            $image = $coaster?->getMainImage();
        }

        if (!$coaster instanceof Coaster || !$image instanceof Image) {
            return null;
        }

        return [
            'type' => $type,
            'coasterId' => $coaster->getId(),
            'coasterSlug' => $coaster->getSlug(),
            'coasterName' => $coaster->getName(),
            'parkName' => $coaster->getPark()?->getName(),
            'imageFilename' => $image->getFilename(),
            'imageCredit' => $image->getCredit(),
            'statusName' => $coaster->getStatus()?->getName(),
        ];
    }
}
