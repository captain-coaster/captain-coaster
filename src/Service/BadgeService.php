<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Badge;
use App\Entity\RiddenCoaster;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Repository\BadgeRepository;
use Doctrine\ORM\EntityManagerInterface;

class BadgeService
{
    final public const string BADGE_TYPE_RATING = 'rating';
    final public const string BADGE_RATING_1 = 'badge.rating1';
    final public const string BADGE_RATING_100 = 'badge.rating100';
    final public const string BADGE_RATING_250 = 'badge.rating250';
    final public const string BADGE_RATING_500 = 'badge.rating500';
    final public const string BADGE_RATING_1000 = 'badge.rating1000';

    final public const string BADGE_TYPE_TEAM = 'team';
    final public const string BADGE_TEAM_KATUN = 'badge.teamkatun';
    final public const string BADGE_TEAM_ISPEED = 'badge.teamispeed';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifService,
        private readonly BadgeRepository $badgeRepository
    ) {
    }

    /** Give badges to User. */
    public function give(User $user): void
    {
        // Give rating badges
        $this->giveRatingBadge($user);

        // Give team badges
        $this->giveTeamBadge($user);

        $this->em->persist($user);
        $this->em->flush();
    }

    /** Give rating badges to User. */
    private function giveRatingBadge(User $user): void
    {
        $ratingNumber = \count($user->getRatings());

        if ($ratingNumber >= 1) {
            $this->addNewBadge($user, self::BADGE_RATING_1);
        }
        if ($ratingNumber >= 100) {
            $this->addNewBadge($user, self::BADGE_RATING_100);
        }
        if ($ratingNumber >= 250) {
            $this->addNewBadge($user, self::BADGE_RATING_250);
        }
        if ($ratingNumber >= 500) {
            $this->addNewBadge($user, self::BADGE_RATING_500);
        }
        if ($ratingNumber >= 1000) {
            $this->addNewBadge($user, self::BADGE_RATING_1000);
        }
    }

    /** Give team badges to User. */
    private function giveTeamBadge(User $user): void
    {
        // Check for already given Team badge
        $currentBadge = $user->getBadges()->filter(
            fn (Badge $badge) => self::BADGE_TYPE_TEAM == $badge->getType()
        );

        // You can be only in one team !
        if (1 == $currentBadge->count()) {
            return;
        }

        // Check in Top first (priority)
        if (null !== $user->getMainTop()) {
            /** @var TopCoaster $topCoaster */
            foreach ($user->getMainTop()->getTopCoasters() as $topCoaster) {
                if ('Katun' === $topCoaster->getCoaster()->getName()) {
                    $katun = $topCoaster->getPosition();
                }
                if ('iSpeed' === $topCoaster->getCoaster()->getName()) {
                    $ispeed = $topCoaster->getPosition();
                }
            }
        }

        // Lowest position wins
        if (!empty($katun) && !empty($ispeed)) {
            if ($katun < $ispeed) {
                $this->addNewBadge($user, self::BADGE_TEAM_KATUN);
            } elseif ($ispeed < $katun) {
                $this->addNewBadge($user, self::BADGE_TEAM_ISPEED);
            }

            // stop here
            return;
        }

        // check then in ratings
        /** @var RiddenCoaster $rating */
        foreach ($user->getRatings() as $rating) {
            if ('Katun' === $rating->getCoaster()->getName()) {
                $katun = $rating->getValue();
            }
            if ('iSpeed' === $rating->getCoaster()->getName()) {
                $ispeed = $rating->getValue();
            }
        }

        // Highest rating wins
        if (!empty($katun) && !empty($ispeed)) {
            if ($katun > $ispeed) {
                $this->addNewBadge($user, self::BADGE_TEAM_KATUN);
            } elseif ($ispeed > $katun) {
                $this->addNewBadge($user, self::BADGE_TEAM_ISPEED);
            }
        }
    }

    /** Helper to add only new badge. */
    private function addNewBadge(User $user, string $badgeName): void
    {
        $badge = $this->badgeRepository->findOneBy(['name' => $badgeName]);

        if (!$user->getBadges()->contains($badge)) {
            $user->addBadge($badge);

            $this->notifService->send(
                $user,
                'notif.badge.message',
                $badgeName,
                $this->notifService::NOTIF_BADGE
            );
        }
    }
}
