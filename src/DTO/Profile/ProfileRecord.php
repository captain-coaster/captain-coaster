<?php

declare(strict_types=1);

namespace App\DTO\Profile;

use App\Entity\Coaster;

/**
 * One line of a member's records: an extreme among the coasters they rode.
 */
final readonly class ProfileRecord
{
    public const string TALLEST = 'tallest';
    public const string FASTEST = 'fastest';
    public const string LONGEST = 'longest';
    public const string INVERSIONS = 'inversions';
    public const string OLDEST = 'oldest';

    /** @param int $value metres, km/h, metres, inversions or opening year */
    public function __construct(
        public string $kind,
        public int $value,
        public Coaster $coaster,
    ) {
    }
}
