<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\Vocabulary;
use Symfony\Component\Intl\Countries;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Labels of the database vocabularies, in the member's language (#145).
 *
 * $locale defaults to the current request locale (\Locale::getDefault(), which Symfony keeps in sync).
 * The scalar variants serve rows selected without their entity.
 */
class VocabularyLabeler
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function label(Vocabulary $term, ?string $locale = null): string
    {
        return $term instanceof Country
            ? $this->country($term->getCode(), $term->getName(), $locale)
            : $this->term($term->getCode(), $term->getName(), $locale);
    }

    /** Translation of the code in the `database` domain, else the English name. */
    public function term(?string $code, string $name, ?string $locale = null): string
    {
        if (null === $code) {
            return $name;
        }

        $label = $this->translator->trans($code, [], 'database', $locale);

        return $label === $code ? $name : $label;
    }

    /** Symfony Intl name of the ISO code, else the English name. */
    public function country(?string $code, string $name, ?string $locale = null): string
    {
        return null !== $code && Countries::exists($code) ? Countries::getName($code, $locale) : $name;
    }

    /**
     * Sorts in the alphabetical order of the language: SQL can't, the order depends on the locale.
     *
     * @template T
     *
     * @param array<T>            $items
     * @param callable(T): string $label
     *
     * @return list<T>
     */
    public function sortByLabel(array $items, callable $label, ?string $locale = null): array
    {
        $collator = new \Collator($locale ?? \Locale::getDefault());
        usort($items, static fn ($a, $b): int => (int) $collator->compare($label($a), $label($b)));

        return $items;
    }
}
