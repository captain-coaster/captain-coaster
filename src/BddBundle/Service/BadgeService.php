<?php

namespace BddBundle\Service;

use BddBundle\Entity\Badge;
use BddBundle\Entity\ListeCoaster;
use BddBundle\Entity\RiddenCoaster;
use BddBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class BadgeService
 * @package BddBundle\Service
 */
class BadgeService
{
    CONST BADGE_TYPE_RATING = 'rating';
    CONST BADGE_RATING_1 = 'badge.rating1';
    CONST BADGE_RATING_100 = 'badge.rating100';
    CONST BADGE_RATING_250 = 'badge.rating250';
    CONST BADGE_RATING_500 = 'badge.rating500';
    CONST BADGE_RATING_1000 = 'badge.rating1000';

    CONST BADGE_TYPE_TEAM = 'team';
    CONST BADGE_TEAM_KATUN = 'badge.teamkatun';
    CONST BADGE_TEAM_ISPEED = 'badge.teamispeed';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var NotificationService
     */
    private $notifService;

    /**
     * BadgeService constructor.
     * @param EntityManagerInterface $em
     * @param NotificationService $notifService
     */
    public function __construct(EntityManagerInterface $em, NotificationService $notifService)
    {
        $this->em = $em;
        $this->notifService = $notifService;
    }

    /**
     * Give badges to User
     *
     * @param User $user
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
     *
     * @param User $user
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
     *
     * @param User $user
     */
    private function giveTeamBadge(User $user)
    {
        // Check for already given Team badge
        $currentBadge = $user->getBadges()->filter(
            function (Badge $badge) {
                return $badge->getType() == self::BADGE_TYPE_TEAM;
            }
        );

        // You can be only in one team !
        if ($currentBadge->count() == 1) {
            return;
        }

        // Check in Top first (priority)
        if (!is_null($user->getMainListe())) {
            /** @var ListeCoaster $listeCoaster */
            foreach ($user->getMainListe()->getListeCoasters() as $listeCoaster) {
                if ($listeCoaster->getCoaster()->getName() === 'Katun') {
                    $katun = $listeCoaster->getPosition();
                }
                if ($listeCoaster->getCoaster()->getName() === 'iSpeed') {
                    $ispeed = $listeCoaster->getPosition();
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
     *
     * @param User $user
     * @param string $badgeName
     */
    private function addNewBadge(User $user, string $badgeName)
    {
        $badge = $this->em->getRepository('BddBundle:Badge')->findOneBy(['name' => $badgeName]);

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
