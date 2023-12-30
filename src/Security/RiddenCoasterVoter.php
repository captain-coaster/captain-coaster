<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class RiddenCoasterVoter extends Voter
{
    final public const UPDATE = 'update';
    final public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::UPDATE, self::DELETE])) {
            return false;
        }

        return $subject instanceof RiddenCoaster;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var RiddenCoaster $review */
        $review = $subject;

        return match ($attribute) {
            self::UPDATE => $this->canUpdate($review, $user),
            self::DELETE => $this->canDelete($review, $user),
            default => throw new \LogicException('This code should not be reached!'),
        };
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
