<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Trait for repositories that provide filter dropdown data.
 */
trait FilterableRepositoryTrait
{
    /**
     * Get entities for filter dropdown.
     *
     * @return array<array{id: int, name: string}>
     */
    public function findForFilter(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id', 'e.name')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Rows of a translated vocabulary. Unsorted: FilterService sorts by translated label.
     *
     * @return array<array{id: int, code: ?string, name: string}>
     */
    public function findVocabularyForFilter(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id', 'e.code', 'e.name')
            ->getQuery()
            ->getArrayResult();
    }
}
