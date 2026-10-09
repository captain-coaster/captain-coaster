<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\DatePrecision;

/**
 * A date known to the day, the month or the year, written `2026-05-17`, `2026-05` or `2026`.
 */
final readonly class PartialDate implements \Stringable
{
    public function __construct(
        public \DateTimeImmutable $date,
        public DatePrecision $precision,
    ) {
    }

    /** @throws \InvalidArgumentException when the text is not a date in one of the three forms */
    public static function fromString(string $text): self
    {
        if (1 !== preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/', trim($text), $parts)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not YYYY, YYYY-MM or YYYY-MM-DD.', $text));
        }

        $year = (int) $parts[1];
        $month = (int) ($parts[2] ?? 1);
        $day = (int) ($parts[3] ?? 1);

        if (!checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a real date.', $text));
        }

        return new self(
            new \DateTimeImmutable()->setDate($year, $month, $day)->setTime(0, 0),
            match (\count($parts)) {
                2 => DatePrecision::Year,
                3 => DatePrecision::Month,
                default => DatePrecision::Day,
            },
        );
    }

    public function __toString(): string
    {
        return $this->date->format($this->precision->format());
    }
}
