<?php

namespace App\Service;

use App\Entity\Badge;
use App\Entity\TopCoaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class BadgeService
 * @package App\Service
 */
class BadgeService
{
    final public const BADGE_TYPE_RATING = 'rating';
    final public const BADGE_RATING_1 = 'badge.rating1';
    final public const BADGE_RATING_100 = 'badge.rating100';
    final public const BADGE_RATING_250 = 'badge.rating250';
    final public const BADGE_RATING_500 = 'badge.rating500';
    final public const BADGE_RATING_1000 = 'badge.rating1000';

    final public const BADGE_TYPE_TEAM = 'team';
    final public const BADGE_TEAM_KATUN = 'badge.teamkatun';
    final public const BADGE_TEAM_ISPEED = 'badge.teamispeed';

    /**
     * BadgeService constructor.
     */
    public function __construct(EntityManagerInterface $em, NotificationService $notifService)
    {
    }

    /**
     * Give badges to User
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function give(User $user)
    {
        // Give rating badges
        $this->giveRatingBadge($user);

        // Give team badges
        $this->giveTeamBadge($user);

        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * Give rating badges to User
     */
    private function giveRatingBadge(User $user)
    {
        $ratingNumber = count($user->getRatings());

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

    /**
     * Give team badges to User
     */
    private function giveTeamBadge(User $user)
    {
        // Check for already given Team badge
        $currentBadge = $user->getBadges()->filter(
            fn(Badge $badge) => $badge->getType() == self::BADGE_TYPE_TEAM
        );

        // You can be only in one team !
        if ($currentBadge->count() == 1) {
            return;
        }

        // Check in Top first (priority)
        if (!is_null($user->getMainTop())) {
            /** @var TopCoaster $topCoaster */
            foreach ($user->getMainTop()->getTopCoasters() as $topCoaster) {
                if ($topCoaster->getCoaster()->getName() === 'Katun') {
                    $katun = $topCoaster->getPosition();
                }
                if ($topCoaster->getCoaster()->getName() === 'iSpeed') {
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
            if ($rating->getCoaster()->getName() === 'Katun') {
                $katun = $rating->getValue();
            }
            if ($rating->getCoaster()->getName() === 'iSpeed') {
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

    /**
     * Helper to add only new badge
     */
    private function addNewBadge(User $user, string $badgeName)
    {
        $badge = $this->Repository->findOneBy(['name' => $badgeName]);

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
