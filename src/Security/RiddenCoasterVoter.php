<?php

namespace App\Security;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RiddenCoasterVoter extends Voter
{
    const UPDATE = 'update';
    const DELETE = 'delete';

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::UPDATE, self::DELETE])) {
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

        /** @var RiddenCoaster $review */
        $review = $subject;

        switch ($attribute) {
            case self::UPDATE:
                return $this->canUpdate($review, $user);
            case self::DELETE:
                return $this->canDelete($review, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param RiddenCoaster $review
     * @param User $user
     * @return bool
     */
    private function canUpdate(RiddenCoaster $review, User $user)
    {
        return $user === $review->getUser();
    }

    /**
     * @param RiddenCoaster $review
     * @param User $user
     * @return bool
     */
    private function canDelete(RiddenCoaster $review, User $user)
    {
        return $user === $review->getUser();
    }
}
