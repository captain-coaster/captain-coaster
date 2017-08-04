<?php

namespace BddBundle\Security;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RatingCoasterVoter extends Voter
{
    const RATE = 'rate';

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::RATE])) {
            return false;
        }

        if (!$subject instanceof Coaster) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Coaster $coaster */
        $coaster = $subject;

        switch ($attribute) {
            case self::RATE:
                return $this->canRate($coaster, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canRate(Coaster $coaster, User $user)
    {
        return $coaster->isRateable();
    }
}