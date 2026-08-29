<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves which review languages a viewer wants to see written text for.
 * An empty preference (the default, for every anonymous visitor and every
 * logged-in user who hasn't customized it) means "only my own language" --
 * preferredLocale when logged in, the current page's locale otherwise.
 */
class ReviewLanguagePreferenceService
{
    public function __construct(private readonly Security $security)
    {
    }

    /** @return array<string> */
    public function resolve(Request $request): array
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $preferred = $user->getPreferredReviewLanguages();

            return [] === $preferred ? [$user->getPreferredLocale()] : $preferred;
        }

        return [$request->getLocale()];
    }
}
