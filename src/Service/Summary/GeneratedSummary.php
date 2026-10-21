<?php

declare(strict_types=1);

namespace App\Service\Summary;

use App\Service\Ai\AiResult;

/**
 * A summary the model wrote, not stored yet.
 */
final readonly class GeneratedSummary
{
    /**
     * @param list<string> $pros
     * @param list<string> $cons
     * @param int          $reviewsAnalyzed same-language reviews sent: what is stored, and what isDue() compares against
     * @param int          $reviewsSent     with the reviews of other languages that filled in a thin sample
     */
    public function __construct(
        public string $summary,
        public ?string $metaDescription,
        public array $pros,
        public array $cons,
        public int $reviewsAnalyzed,
        public int $reviewsSent,
        public AiResult $ai,
    ) {
    }
}
