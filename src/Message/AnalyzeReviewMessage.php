<?php

declare(strict_types=1);

namespace App\Message;

final class AnalyzeReviewMessage
{
    public function __construct(
        public readonly int $reviewId,
    ) {
    }
}
