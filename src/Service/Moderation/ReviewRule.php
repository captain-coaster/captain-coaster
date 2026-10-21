<?php

declare(strict_types=1);

namespace App\Service\Moderation;

/**
 * The rule of the review policy (config/moderation/review-policy.md) a review text falls under:
 * what the AI answers, and the reason of the report it leads to.
 */
enum ReviewRule: string
{
    case None = 'none';
    case GrayZone = 'gray_zone';
    case NotAReview = 'not_a_review';
    case Attack = 'attack';
    case Sexual = 'sexual';
    case NotRidden = 'not_ridden';
    case HarmfulOfftopic = 'harmful_offtopic';

    /** The text is visible unless the rule hides it; a gray zone is visible and goes to a moderator. */
    public function hides(): bool
    {
        return !\in_array($this, [self::None, self::GrayZone], true);
    }
}
