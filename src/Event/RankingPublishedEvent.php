<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Dispatched once a monthly ranking is published: its ranks are on the site.
 */
final class RankingPublishedEvent
{
    public function __construct(
        public readonly ?string $highlightedCoasterName = null,
        // A regenerated ranking published again: riders were already notified
        public readonly bool $republished = false,
    ) {
    }
}
