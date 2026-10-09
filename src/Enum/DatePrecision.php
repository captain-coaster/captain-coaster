<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How much of a stored date is known. The unknown part is stored as the 1st.
 */
enum DatePrecision: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    /** The last day of the period the date stands for. */
    public function lastDay(\DateTimeInterface $date): \DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);

        return match ($this) {
            self::Day => $day,
            self::Month => $day->modify('last day of this month'),
            self::Year => $day->setDate((int) $day->format('Y'), 12, 31),
        };
    }

    /** The PHP date format that writes the known part only. */
    public function format(): string
    {
        return match ($this) {
            self::Day => 'Y-m-d',
            self::Month => 'Y-m',
            self::Year => 'Y',
        };
    }
}
