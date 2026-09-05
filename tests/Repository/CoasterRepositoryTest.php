<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Repository\CoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CoasterRepository::findForRanking().
 *
 * Uses a real ManagerRegistry/ClassMetadata pair (rather than the simpler
 * onlyMethods(['getEntityManager']) partial mock used elsewhere) because
 * createBaseQuery() calls the $this->createQueryBuilder('c') shorthand,
 * which -- unlike $this->getEntityManager()->createQueryBuilder() -- goes
 * through ServiceEntityRepository's own resolveRepository()/registry
 * lookup, not the overridden getEntityManager().
 */
class CoasterRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CoasterRepository $repository;

    /** @var list<string> */
    private array $capturedDql = [];

    /** @var list<array{lifetime: ?int}> */
    private array $capturedResultCacheCalls = [];

    /** @var array<string, mixed> */
    private array $capturedHints = [];

    protected function setUp(): void
    {
        $this->capturedDql = [];
        $this->capturedResultCacheCalls = [];
        $this->capturedHints = [];

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );
        $this->em->method('getClassMetadata')->willReturn(new ClassMetadata(Coaster::class));
        // Real Expr builder -- applyUserFilters() calls $qb->expr()->in(...),
        // and a mocked EntityManager's unstubbed getExpressionBuilder()
        // would otherwise hand back a fake object the real DQL builder
        // rejects with "Expression of type '...' not allowed in this context".
        $this->em->method('getExpressionBuilder')->willReturn(new Expr());

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $this->repository = new CoasterRepository($registry);
    }

    /** @param array<int, mixed> $rows */
    private function stubQueries(int $count, array $rows = []): void
    {
        $this->em->method('createQuery')->willReturnCallback(function (string $dql) use ($count, $rows) {
            $this->capturedDql[] = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setParameter')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('setHint')->willReturnCallback(function (string $name, mixed $value) use ($query) {
                $this->capturedHints[$name] = $value;

                return $query;
            });
            $query->method('enableResultCache')->willReturnCallback(function (?int $lifetime) use ($query) {
                $this->capturedResultCacheCalls[] = ['lifetime' => $lifetime];

                return $query;
            });

            if (str_contains($dql, 'count(c.id)')) {
                $query->method('getSingleScalarResult')->willReturn($count);
            } else {
                $query->method('getResult')->willReturn($rows);
            }

            return $query;
        });
    }

    private function mainEntityDql(): string
    {
        foreach ($this->capturedDql as $dql) {
            if (!str_contains($dql, 'count(c.id)')) {
                return $dql;
            }
        }

        $this->fail('No main entity query was captured.');
    }

    public function testFetchJoinsCountrySeatingTypeAndMainImageToAvoidNPlusOne(): void
    {
        $this->stubQueries(0);

        $this->repository->findForRanking();

        $dql = $this->mainEntityDql();

        // Regression guard: these three were previously joined (for filtering)
        // but not selected, so Twig's coaster.park.country / .seatingType /
        // .mainImage access lazy-loaded them one row at a time. Anchored to
        // the SELECT clause itself (not just "appears somewhere in the DQL"),
        // since the join aliases also appear in the FROM/JOIN clauses
        // regardless of whether they're selected.
        $matched = preg_match('/^SELECT (.*?) FROM /', $dql, $matches);
        $this->assertSame(1, $matched, "Could not find a SELECT clause in DQL: $dql");
        $selectedAliases = array_map('trim', explode(',', $matches[1]));

        $this->assertContains('country', $selectedAliases);
        $this->assertContains('st', $selectedAliases);
        $this->assertContains('mi', $selectedAliases);
    }

    public function testSetsKnpPaginatorCountHintFromASeparateCountQuery(): void
    {
        $this->stubQueries(42);

        $this->repository->findForRanking();

        $this->assertSame(42, $this->capturedHints['knp_paginator.count']);
    }

    public function testCachesWhenNoUserSpecificFilterIsApplied(): void
    {
        $this->stubQueries(0);

        // filters['user'] alone (sent by the frontend for every logged-in
        // visitor) must not disable caching -- only an actual ridden/notridden
        // toggle does, since that's the only thing that changes the query.
        $this->repository->findForRanking(['user' => 42]);

        $this->assertNotEmpty($this->capturedResultCacheCalls, 'Expected both the count and main queries to be cached');
        foreach ($this->capturedResultCacheCalls as $call) {
            $this->assertSame(300, $call['lifetime']);
        }
    }

    public function testDoesNotCacheWhenTheRiddenFilterIsActive(): void
    {
        $this->stubQueries(0);

        $this->repository->findForRanking(['user' => 42, 'ridden' => 'on']);

        $this->assertSame([], $this->capturedResultCacheCalls);
    }

    public function testDoesNotCacheWhenTheNotRiddenFilterIsActive(): void
    {
        $this->stubQueries(0);

        $this->repository->findForRanking(['user' => 42, 'notridden' => 'on']);

        $this->assertSame([], $this->capturedResultCacheCalls);
    }
}
