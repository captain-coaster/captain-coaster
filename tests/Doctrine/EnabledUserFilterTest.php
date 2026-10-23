<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Doctrine\EnabledUserFilter;
use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;

class EnabledUserFilterTest extends TestCase
{
    public function testHidesContentOfDisabledOwners(): void
    {
        $this->assertSame('r.user_id NOT IN (SELECT id FROM users WHERE enabled = 0)', $this->constraint(RiddenCoaster::class, 'user', 'user_id', 'r'));
        $this->assertSame('i.uploader_id NOT IN (SELECT id FROM users WHERE enabled = 0)', $this->constraint(Image::class, 'uploader', 'uploader_id', 'i'));
    }

    public function testLeavesOtherEntitiesAlone(): void
    {
        $this->assertSame('', $this->constraint(Coaster::class, null, null, 'c'));
    }

    private function constraint(string $class, ?string $field, ?string $column, string $alias): string
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn($class);
        if (null !== $field) {
            $metadata->method('getSingleAssociationJoinColumnName')->willReturnMap([[$field, $column]]);
        }

        return new EnabledUserFilter($this->createStub(EntityManagerInterface::class))->addFilterConstraint($metadata, $alias);
    }
}
