<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Top;
use App\Repository\TopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TopRepository::getTopWithData().
 */
class TopRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TopRepository&MockObject $repository;

    private string $capturedDql = '';

    protected function setUp(): void
    {
        $this->capturedDql = '';

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );
        $this->em->method('createQuery')->willReturnCallback(function (string $dql) {
            $this->capturedDql = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setParameter')->willReturnSelf();
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getSingleResult')->willReturn(new Top());

            return $query;
        });

        $this->repository = $this->getMockBuilder(TopRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $this->repository->method('getEntityManager')->willReturn($this->em);
    }

    public function testGetTopWithDataFetchJoinsCoasterManufacturerParkCountryAndMainImage(): void
    {
        $top = new Top();

        $this->repository->getTopWithData($top);

        // Regression guard: Top/show.html.twig reads topCoaster.coaster's
        // .manufacturer / .park(.country) / .mainImage for every coaster in
        // the list -- each of these, if not selected here, lazy-loads on its
        // own the moment the template touches it.
        $matched = preg_match('/^SELECT (.*?) FROM /', $this->capturedDql, $matches);
        $this->assertSame(1, $matched, "Could not find a SELECT clause in DQL: {$this->capturedDql}");
        $selectedAliases = array_map('trim', explode(',', $matches[1]));

        foreach (['tc', 'c', 'm', 'p', 'co', 'mi'] as $alias) {
            $this->assertContains($alias, $selectedAliases, "Expected alias '$alias' to be fetch-joined in: {$this->capturedDql}");
        }
    }
}
