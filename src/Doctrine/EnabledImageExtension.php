<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Image;
use Doctrine\ORM\QueryBuilder;

/**
 * The API serves published photos only, as the site does: one awaiting moderation or hidden is not found.
 */
final class EnabledImageExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->onlyEnabled($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->onlyEnabled($queryBuilder, $resourceClass);
    }

    private function onlyEnabled(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Image::class !== $resourceClass) {
            return;
        }

        $queryBuilder->andWhere(\sprintf('%s.enabled = true', $queryBuilder->getRootAliases()[0]));
    }
}
