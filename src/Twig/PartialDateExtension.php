<?php

declare(strict_types=1);

namespace App\Twig;

use App\DTO\PartialDate;
use App\Enum\DatePrecision;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Writes a partial date with what is known of it: "2026", "May 2026" or the full short date.
 */
class PartialDateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('partial_date', $this->format(...))];
    }

    /** $locale defaults to the current request locale (\Locale::getDefault(), which Symfony keeps in sync). */
    public function format(?PartialDate $partialDate, ?string $locale = null): string
    {
        if (null === $partialDate) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            $locale ?? \Locale::getDefault(),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE,
            $partialDate->date->getTimezone(),
            pattern: match ($partialDate->precision) {
                DatePrecision::Day => null,
                // Standalone month name: "mai 2026", not an inflected form.
                DatePrecision::Month => 'LLLL y',
                DatePrecision::Year => 'y',
            },
        );

        $formatted = $formatter->format($partialDate->date);
        if (false === $formatted) {
            throw new \RuntimeException(\sprintf('Failed to format the date "%s": %s', $partialDate, $formatter->getErrorMessage()));
        }

        return $formatted;
    }
}
