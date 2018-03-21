<?php

namespace BddBundle\Security;

use BddBundle\Entity\Report;
use BddBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ReportVoter extends Voter
{
    const EDIT = 'edit';
    const LIKE = 'like';

    /**
     * @var AccessDecisionManagerInterface
     */
    private $decisionManager;

    /**
     * ReportVoter constructor.
     * @param AccessDecisionManagerInterface $decisionManager
     */
    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @return bool
     */
    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::EDIT])) {
            return false;
        }

        if (!$subject instanceof Report) {
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

        if ($this->decisionManager->decide($token, array('ROLE_SUPER_ADMIN'))) {
            return true;
        }

        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($subject, $user);
            case self::LIKE:
                return $this->canLike($subject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    /**
     * @param Report $report
     * @param User $user
     * @return bool
     */
    private function canEdit(Report $report, User $user)
    {
        return $user === $report->getUser();
    }

    private function canLike(Report $report, User $user)
    {
        return $user !== $report->getUser();
    }
}
