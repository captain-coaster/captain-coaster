<?php

namespace BddBundle\Security;

use BddBundle\Entity\Liste;
use BddBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ListeVoter extends Voter
{
    const EDIT = 'edit';
    const EDIT_DETAILS = 'edit-details';

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::EDIT, self::EDIT_DETAILS])) {
            return false;
        }

        if (!$subject instanceof Liste) {
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

        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($subject, $user);
            case self::EDIT_DETAILS:
                return $this->canEditDetails($subject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param Liste $liste
     * @param User $user
     * @return bool
     */
    private function canEdit(Liste $liste, User $user)
    {
        return $user === $liste->getUser();
    }

    /**
     * @param Liste $liste
     * @param User $user
     * @return bool
     */
    private function canEditDetails(Liste $liste, User $user)
    {
        return $user === $liste->getUser() && $liste->isMain() === false;
    }
}
