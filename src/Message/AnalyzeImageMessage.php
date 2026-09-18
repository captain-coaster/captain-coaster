<?php

declare(strict_types=1);

namespace App\Message;

final class AnalyzeImageMessage
{
    public function __construct(
        public readonly int $imageId,
    ) {
    }
}
