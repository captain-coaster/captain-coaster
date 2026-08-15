<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use Doctrine\ORM\NoResultException;

/**
 * Picks the homepage hero content from 4 rotating sources:
 *   - upcoming : a coaster opening in the next 90 days
 *   - new      : a coaster opened in the last 60 days
 *   - hot      : a coaster trending (most rated this week)
 *   - photo    : the latest liked picture
 *
 * Each candidate is fetched from a Doctrine result cache (1h TTL).
 * The final selection is random among available candidates.
 */
class HeroService
{
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    /**
     * @param array<int, Coaster> $excludeFromHot Coasters already shown elsewhere (e.g. top ranked)
     *
     * @return array{type: string, coaster?: Coaster, image?: Image}|null
     */
    public function pick(array $excludeFromHot = []): ?array
    {
        $candidates = [];

        $upcoming = $this->coasterRepository->findUpcomingCoaster();
        if ($upcoming) {
            $candidates[] = ['type' => 'upcoming', 'coaster' => $upcoming];
        }

        $recent = $this->coasterRepository->findRecentlyOpenedCoaster();
        if ($recent) {
            $candidates[] = ['type' => 'new', 'coaster' => $recent];
        }

        $excludeIds = array_map(static fn (Coaster $c) => $c->getId(), $excludeFromHot);
        $hot = $this->coasterRepository->findTrendingCoaster($excludeIds);
        if ($hot) {
            $candidates[] = ['type' => 'hot', 'coaster' => $hot];
        }

        try {
            $image = $this->imageRepository->findLatestLikedImage();
            $candidates[] = ['type' => 'photo', 'image' => $image];
        } catch (NoResultException) {
            // no liked image yet — skip
        }

        if (empty($candidates)) {
            return null;
        }

        return $candidates[array_rand($candidates)];
    }
}
