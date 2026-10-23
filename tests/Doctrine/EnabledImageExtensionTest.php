<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Doctrine\EnabledImageExtension;
use App\Entity\Coaster;
use App\Entity\Image;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class EnabledImageExtensionTest extends TestCase
{
    public function testImagesAreLimitedToPublishedOnes(): void
    {
        foreach (['collection', 'item'] as $operation) {
            $queryBuilder = $this->createMock(QueryBuilder::class);
            $queryBuilder->method('getRootAliases')->willReturn(['o']);
            $queryBuilder->expects($this->once())->method('andWhere')->with('o.enabled = true');

            $this->apply($operation, $queryBuilder, Image::class);
        }
    }

    public function testOtherResourcesAreLeftAlone(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->never())->method('andWhere');

        $this->apply('collection', $queryBuilder, Coaster::class);
    }

    /** @param class-string $resourceClass */
    private function apply(string $operation, QueryBuilder $queryBuilder, string $resourceClass): void
    {
        $extension = new EnabledImageExtension();
        $names = $this->createStub(QueryNameGeneratorInterface::class);
        'item' === $operation
            ? $extension->applyToItem($queryBuilder, $names, $resourceClass, ['id' => 1])
            : $extension->applyToCollection($queryBuilder, $names, $resourceClass);
    }
}
