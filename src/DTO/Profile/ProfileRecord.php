<?php

declare(strict_types=1);

namespace App\DTO\Profile;

use App\Entity\Coaster;

/**
 * One line of a member's records: an extreme among the coasters they rode, or their most ridden manufacturer.
 */
final readonly class ProfileRecord
{
    public const string TALLEST = 'tallest';
    public const string FASTEST = 'fastest';
    public const string LONGEST = 'longest';
    public const string INVERSIONS = 'inversions';
    public const string OLDEST = 'oldest';
    public const string MANUFACTURER = 'manufacturer';

    /**
     * @param int      $value   metres, km/h, metres, inversions, opening year, or coasters ridden (manufacturer)
     * @param ?Coaster $coaster null for the manufacturer
     * @param ?string  $name    the manufacturer's name, null otherwise
     * @param ?int     $share   percent of the world record (0-100), null where it has no meaning (oldest, manufacturer)
     */
    public function __construct(
        public string $kind,
        public int $value,
        public ?Coaster $coaster = null,
        public ?string $name = null,
        public ?int $share = null,
    ) {
    }
}
