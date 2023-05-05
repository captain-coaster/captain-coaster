<?php

namespace App\Security;

use App\Entity\Top;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TopVoter extends Voter
{
    const EDIT = 'edit';
    const EDIT_DETAILS = 'edit-details';
    const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::EDIT, self::EDIT_DETAILS, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof Top) {
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

        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($subject, $user);
            case self::EDIT_DETAILS:
                return $this->canEditDetails($subject, $user);
            case self::DELETE:
                return $this->canDelete($subject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    private function canEdit(Top $top, User $user): bool
    {
        return $user === $top->getUser();
    }

    private function canEditDetails(Top $top, User $user): bool
    {
        return $user === $top->getUser() && $top->isMain() === false;
    }

    private function canDelete(Top $top, User $user): bool
    {
        return $user === $top->getUser() && $top->isMain() === false;
    }
}
