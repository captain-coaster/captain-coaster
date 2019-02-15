<?php

namespace App\Security;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RatingVoter extends Voter
{
    const DELETE = 'delete';

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::DELETE])) {
            return false;
        }

        if (!$subject instanceof RiddenCoaster) {
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

        /** @var RiddenCoaster $rating */
        $rating = $subject;

        switch ($attribute) {
            case self::DELETE:
                return $this->canDelete($rating, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param RiddenCoaster $rating
     * @param User $user
     * @return bool
     */
    private function canDelete(RiddenCoaster $rating, User $user)
    {
        return $user === $rating->getUser();
    }
}
