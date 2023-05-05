<?php

namespace App\Security;

use App\Entity\Coaster;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CoasterVoter extends Voter
{
    final public const RATE = 'rate';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute != self::RATE) {
            return false;
        }
        return $subject instanceof Coaster;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Coaster $coaster */
        $coaster = $subject;

        if ($attribute == self::RATE) {
            return $this->canRate($coaster);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canRate(Coaster $coaster): bool
    {
        return $coaster->isRateable();
    }
}
