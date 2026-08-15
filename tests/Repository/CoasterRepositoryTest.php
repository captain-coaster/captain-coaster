<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CoasterRepositoryTest extends TestCase
{
    private CoasterRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getMockBuilder(CoasterRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();
    }

    public function testFindRandomTopRankedReturnsNullWhenNoResults(): void
    {
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repository->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->findRandomTopRanked(10);

        $this->assertNull($result);
    }

    public function testFindRandomTopRankedReturnsCoasterWhenResultsExist(): void
    {
        $image = new Image();
        $coaster = new Coaster();
        $coaster->setMainImage($image);

        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getResult')->willReturn([$coaster]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repository->method('createQueryBuilder')->willReturn($qb);

        $result = $this->repository->findRandomTopRanked(10);

        $this->assertInstanceOf(Coaster::class, $result);
    }
}
