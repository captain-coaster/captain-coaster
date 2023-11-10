<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Top;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TopVoter extends Voter
{
    final public const EDIT = 'edit';
    final public const EDIT_DETAILS = 'edit-details';
    final public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::EDIT, self::EDIT_DETAILS, self::DELETE])) {
            return false;
        }

        return $subject instanceof Top;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::EDIT => $this->canEdit($subject, $user),
            self::EDIT_DETAILS => $this->canEditDetails($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => throw new \LogicException('This code should not be reached!'),
        };
    }

    private function canEdit(Top $top, User $user): bool
    {
        return $user === $top->getUser();
    }

    private function canEditDetails(Top $top, User $user): bool
    {
        return $user === $top->getUser() && !$top->isMain();
    }

    private function canDelete(Top $top, User $user): bool
    {
        return $user === $top->getUser() && !$top->isMain();
    }
}
