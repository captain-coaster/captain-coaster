<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Service\StatService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes lightweight per-user stats to templates (navigation UserZone).
 * Results are memoized per request so the navigation, which renders on every
 * page, issues at most one COUNT query per user.
 */
class UserStatsExtension extends AbstractExtension
{
    /** @var array<int, int> */
    private array $riddenCountCache = [];

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly StatService $statService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_ridden_count', $this->riddenCount(...)),
            new TwigFunction('profile_rank', $this->profileRank(...)),
        ];
    }

    public function riddenCount(User $user): int
    {
        return $this->riddenCountCache[(int) $user->getId()] ??= $this->riddenCoasterRepository->countForUser($user);
    }

    /** Rank tier key (rookie … legend) for site-wide chips. Cheap while the score is static. */
    public function profileRank(User $user): string
    {
        return $this->statService->computeRank($this->statService->getQualityScore($user))['key'];
    }
}
