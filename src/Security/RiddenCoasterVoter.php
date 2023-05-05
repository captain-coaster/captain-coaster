<?php

namespace App\Security;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RiddenCoasterVoter extends Voter
{
    const UPDATE = 'update';
    const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::UPDATE, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof RiddenCoaster) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
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

    private function canUpdate(RiddenCoaster $review, User $user): bool
    {
        return $user === $review->getUser();
    }

    private function canDelete(RiddenCoaster $review, User $user): bool
    {
        return $user === $review->getUser();
    }
}
