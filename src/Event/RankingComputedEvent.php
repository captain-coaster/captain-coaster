<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Dispatched once a monthly ranking run has actually persisted new ranks.
 */
final class RankingComputedEvent
{
    public function __construct(
        public readonly ?string $highlightedCoasterName = null,
    ) {
    }
}
