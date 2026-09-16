<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use Doctrine\ORM\NoResultException;

/**
 * Picks the homepage hero content from 4 rotating candidates:
 *   - upcoming     : a coaster under construction or officially announced
 *   - new          : a coaster opened in the last 90 days
 *   - trending     : a coaster with a lot of recent ride activity
 *   - photo        : a heavily-liked user photo
 *
 * Each candidate is cached individually (see the repository methods); the
 * final pick among whichever candidates exist is random, so the same type
 * doesn't dominate every visit.
 */
class HeroService
{
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    /** @return array{type: string, coaster?: Coaster, image?: Image}|null */
    public function pick(): ?array
    {
        $candidates = [];

        if ($upcoming = $this->coasterRepository->findUpcomingCoaster()) {
            $candidates[] = ['type' => 'upcoming', 'coaster' => $upcoming];
        }

        if ($recent = $this->coasterRepository->findRecentlyOpenedCoaster()) {
            $candidates[] = ['type' => 'new', 'coaster' => $recent];
        }

        if ($trending = $this->coasterRepository->findTrendingCoaster()) {
            $candidates[] = ['type' => 'trending', 'coaster' => $trending];
        }

        try {
            $candidates[] = ['type' => 'photo', 'image' => $this->imageRepository->findFeaturedImage()];
        } catch (NoResultException) {
            // no photo clears the like threshold yet -- skip
        }

        if ([] === $candidates) {
            return null;
        }

        return $candidates[array_rand($candidates)];
    }
}
