<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use Doctrine\ORM\NoResultException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Picks the homepage hero content from 4 rotating candidates:
 *   - upcoming     : a coaster under construction or officially announced
 *   - new          : a coaster opened in the last 90 days
 *   - trending     : a coaster with a lot of recent ride activity
 *   - photo        : a heavily-liked user photo
 *
 * The pick (including which coaster/image it resolves to) is cached as a
 * whole for an hour, under CACHE_KEY -- not just the 4 candidate queries --
 * so a warm homepage load touches neither the DB nor a Doctrine lazy-load
 * for coaster.mainImage. Showing the same hero for up to an hour is fine;
 * ImageListener calls invalidate() on any image enable/disable/delete so a
 * moderation action takes effect immediately rather than waiting out the TTL.
 */
class HeroService
{
    private const string CACHE_KEY = 'hero_pick';

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
        $candidates = [];

        if (($coaster = $this->coasterRepository->findUpcomingCoaster()) && $coaster->getMainImage()) {
            $candidates[] = ['type' => 'upcoming', 'coaster' => $coaster, 'image' => $coaster->getMainImage()];
        }

        if (($coaster = $this->coasterRepository->findRecentlyOpenedCoaster()) && $coaster->getMainImage()) {
            $candidates[] = ['type' => 'new', 'coaster' => $coaster, 'image' => $coaster->getMainImage()];
        }

        if (($coaster = $this->coasterRepository->findTrendingCoaster()) && $coaster->getMainImage()) {
            $candidates[] = ['type' => 'trending', 'coaster' => $coaster, 'image' => $coaster->getMainImage()];
        }

        try {
            $image = $this->imageRepository->findFeaturedImage();
            $candidates[] = ['type' => 'photo', 'coaster' => $image->getCoaster(), 'image' => $image];
        } catch (NoResultException) {
            // no photo clears the like threshold yet -- skip
        }

        if ([] === $candidates) {
            return null;
        }

        $picked = $candidates[array_rand($candidates)];

        return [
            'type' => $picked['type'],
            'coasterId' => $picked['coaster']->getId(),
            'coasterSlug' => $picked['coaster']->getSlug(),
            'coasterName' => $picked['coaster']->getName(),
            'parkName' => $picked['coaster']->getPark()?->getName(),
            'imageFilename' => $picked['image']->getFilename(),
            'imageCredit' => $picked['image']->getCredit(),
            'statusName' => $picked['coaster']->getStatus()?->getName(),
        ];
    }
}
