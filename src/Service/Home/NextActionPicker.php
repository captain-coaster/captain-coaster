<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;

/**
 * Picks the one call to action of a member's Home.
 *
 * A coaster rated in the last few days without a review comes first. Otherwise the kind of suggestion changes from
 * day to day among those that apply (review, Top, photo, park), and so does the coaster or park it names: each kind
 * goes through its best candidates in turn, so the same one comes back only after the others.
 *
 * Every rule is a constant below; a new kind is a method here, a case in templates/components/Home/NextAction.html.twig
 * and its `action.*` keys in translations/home.
 */
class NextActionPicker
{
    /** A rating this recent without a review beats every other suggestion. */
    private const int REVIEW_FIRST_DAYS = 3;
    /** Ratings without a review stay suggested this long… */
    private const int REVIEW_WINDOW_DAYS = 14;
    /** …or for good from this rating, which also puts a coaster forward for the main Top. */
    private const float FAVORITE_RATING = 4.5;
    private const int TOP_MIN_RATINGS = 10;
    private const int FEW_PHOTOS = 3;
    /** Candidates each kind draws from. */
    private const int CANDIDATES = 10;

    private const array KINDS = ['review', 'top', 'photo', 'park'];

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly TopRepository $topRepository,
        private readonly ParkRepository $parkRepository,
    ) {
    }

    /**
     * @param int $ridden coasters the member has ridden
     *
     * @return array{type: 'review', coaster: Coaster}|array{type: 'photo', coaster: Coaster, photos: int}|array{type: 'top', ridden: int}|array{type: 'top_add', coaster: Coaster, rating: float, top: int}|array{type: 'park', park: array{id: int, name: string, slug: string, total: int, ridden: int}}|null
     */
    public function pick(User $user, int $ridden, \DateTimeImmutable $now): ?array
    {
        if (0 === $ridden) {
            return null;
        }

        $unreviewed = $this->riddenCoasterRepository->findWithoutReview(
            $user,
            $now->modify(\sprintf('-%d days', self::REVIEW_WINDOW_DAYS)),
            self::FAVORITE_RATING,
            self::CANDIDATES,
        );
        if ([] !== $unreviewed && $unreviewed[0]->getCreatedAt() >= $now->modify(\sprintf('-%d days', self::REVIEW_FIRST_DAYS))) {
            return ['type' => 'review', 'coaster' => $unreviewed[0]->getCoaster()];
        }

        // Each kind leads one day in four and then moves to its next candidate. A kind reached by falling through
        // moves every day instead, or a member with a single kind that applies would see one item four days running.
        // The member id offsets both, so members don't all see the same item.
        $day = intdiv($now->getTimestamp(), 86400);
        $offset = $day % \count(self::KINDS);
        foreach ([...\array_slice(self::KINDS, $offset), ...\array_slice(self::KINDS, 0, $offset)] as $i => $kind) {
            $turn = (0 === $i ? intdiv($day, \count(self::KINDS)) : $day) + (int) $user->getId();
            $action = match ($kind) {
                'review' => $this->review($unreviewed, $turn),
                'top' => $this->top($user, $ridden, $turn),
                'photo' => $this->photo($user, $turn),
                'park' => $this->park($user, $turn),
            };
            if (null !== $action) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param list<RiddenCoaster> $unreviewed
     *
     * @return array{type: 'review', coaster: Coaster}|null
     */
    private function review(array $unreviewed, int $turn): ?array
    {
        $rating = self::draw($unreviewed, $turn);

        return null === $rating ? null : ['type' => 'review', 'coaster' => $rating->getCoaster()];
    }

    /**
     * Puts a favorite coaster forward for the main Top, or suggests creating one.
     *
     * @return array{type: 'top', ridden: int}|array{type: 'top_add', coaster: Coaster, rating: float, top: int}|null
     */
    private function top(User $user, int $ridden, int $turn): ?array
    {
        $missing = self::draw($this->riddenCoasterRepository->findMissingFromMainTop($user, self::FAVORITE_RATING, self::CANDIDATES), $turn);
        if (null !== $missing) {
            return ['type' => 'top_add'] + $missing;
        }

        if ($ridden < self::TOP_MIN_RATINGS || $this->topRepository->hasMainTop($user)) {
            return null;
        }

        return ['type' => 'top', 'ridden' => $ridden];
    }

    /** @return array{type: 'photo', coaster: Coaster, photos: int}|null */
    private function photo(User $user, int $turn): ?array
    {
        $found = self::draw($this->riddenCoasterRepository->findWithFewPhotos($user, self::FEW_PHOTOS, self::CANDIDATES), $turn);

        return null === $found ? null : ['type' => 'photo'] + $found;
    }

    /** @return array{type: 'park', park: array{id: int, name: string, slug: string, total: int, ridden: int}}|null */
    private function park(User $user, int $turn): ?array
    {
        $park = self::draw($this->parkRepository->findClosestToCompletion($user, self::CANDIDATES), $turn);

        return null === $park ? null : ['type' => 'park', 'park' => $park];
    }

    /**
     * The candidates in turn: none comes back before the others have had theirs.
     *
     * @template T
     *
     * @param list<T> $candidates
     *
     * @return T|null
     */
    private static function draw(array $candidates, int $turn): mixed
    {
        return [] === $candidates ? null : $candidates[$turn % \count($candidates)];
    }
}
