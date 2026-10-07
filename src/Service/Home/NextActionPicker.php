<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Entity\Coaster;
use App\Entity\User;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;

/**
 * Picks the one call to action of a member's Home. A coaster rated in the last two weeks without a review comes
 * first; otherwise the suggestion changes from day to day among those that apply: create a Top, add a photo,
 * finish a park.
 */
class NextActionPicker
{
    private const int REVIEW_WINDOW_DAYS = 14;
    private const int TOP_MIN_RATINGS = 10;
    private const int FEW_PHOTOS = 3;

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly TopRepository $topRepository,
        private readonly ParkRepository $parkRepository,
    ) {
    }

    /**
     * @param int $ridden coasters the member has ridden
     *
     * @return array{type: 'review', coaster: Coaster}|array{type: 'photo', coaster: Coaster, photos: int}|array{type: 'top', ridden: int}|array{type: 'park', park: array{id: int, name: string, slug: string, total: int, ridden: int}}|null
     */
    public function pick(User $user, int $ridden, \DateTimeImmutable $now): ?array
    {
        if (0 === $ridden) {
            return null;
        }

        $unreviewed = $this->riddenCoasterRepository->findLatestWithoutReview(
            $user,
            $now->modify(\sprintf('-%d days', self::REVIEW_WINDOW_DAYS)),
        );
        if (null !== $unreviewed) {
            return ['type' => 'review', 'coaster' => $unreviewed->getCoaster()];
        }

        $types = ['top', 'photo', 'park'];
        $offset = (int) $now->format('z') % \count($types);

        foreach ([...\array_slice($types, $offset), ...\array_slice($types, 0, $offset)] as $type) {
            $action = match ($type) {
                'top' => $this->top($user, $ridden),
                'photo' => $this->photo($user),
                'park' => $this->park($user),
            };
            if (null !== $action) {
                return $action;
            }
        }

        return null;
    }

    /** @return array{type: 'top', ridden: int}|null */
    private function top(User $user, int $ridden): ?array
    {
        if ($ridden < self::TOP_MIN_RATINGS || $this->topRepository->hasMainTop($user)) {
            return null;
        }

        return ['type' => 'top', 'ridden' => $ridden];
    }

    /** @return array{type: 'photo', coaster: Coaster, photos: int}|null */
    private function photo(User $user): ?array
    {
        $found = $this->riddenCoasterRepository->findLatestWithFewPhotos($user, self::FEW_PHOTOS);

        return null === $found ? null : ['type' => 'photo'] + $found;
    }

    /** @return array{type: 'park', park: array{id: int, name: string, slug: string, total: int, ridden: int}}|null */
    private function park(User $user): ?array
    {
        $park = $this->parkRepository->findClosestToCompletion($user);

        return null === $park ? null : ['type' => 'park', 'park' => $park];
    }
}
