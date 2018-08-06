<?php

namespace BddBundle\Security;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CoasterVoter extends Voter
{
    const RATE = 'rate';

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
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

    /**
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     * @return bool
     */
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
                return $this->canRate($coaster);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param Coaster $coaster
     * @return bool
     */
    private function canRate(Coaster $coaster)
    {
        return $coaster->isRateable();
    }
}
