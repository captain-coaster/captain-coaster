<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\Park;
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
 * Unit tests for CoasterRepository::findForRanking() and findForSearch().
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

    /** @var list<array{lifetime: ?int, isCount: bool}> */
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
            $query->method('enableResultCache')->willReturnCallback(function (?int $lifetime) use ($query, $dql) {
                $this->capturedResultCacheCalls[] = ['lifetime' => $lifetime, 'isCount' => str_contains($dql, 'count(c.id)')];

                return $query;
            });

            if (str_contains($dql, 'count(c.id)')) {
                $query->method('getSingleScalarResult')->willReturn($count);
            } else {
                $query->method('getResult')->willReturn($rows);
                $query->method('getOneOrNullResult')->willReturn($rows[0] ?? null);
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

    public function testFindForSearchFetchJoinsParkCountryManufacturerMaterialTypeAndMainImage(): void
    {
        $this->stubQueries(0);

        $this->repository->findForSearch();

        $dql = $this->mainEntityDql();

        // Regression guard: these were previously joined (for filtering) but
        // not selected, so Twig's coaster.park / .park.country / .manufacturer
        // / .materialType / .mainImage access in results.html.twig lazy-loaded
        // them one row at a time -- up to 20 rows per search page.
        $matched = preg_match('/^SELECT (.*?) FROM /', $dql, $matches);
        $this->assertSame(1, $matched, "Could not find a SELECT clause in DQL: $dql");
        $selectedAliases = array_map('trim', explode(',', $matches[1]));

        $this->assertContains('p', $selectedAliases);
        $this->assertContains('country', $selectedAliases);
        $this->assertContains('m', $selectedAliases);
        $this->assertContains('mt', $selectedAliases);
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

    public function testFindForShowFetchJoinsEveryAssociationTheTemplateRenders(): void
    {
        $this->stubQueries(0);

        $this->repository->findForShow(1);

        $dql = $this->mainEntityDql();

        // Regression guard: show.html.twig reads coaster.park(.country) /
        // .manufacturer / .materialType / .seatingType / .model / .status /
        // .restraint / .currency / .launchs directly -- each of these, if
        // not selected here, lazy-loads on its own the moment the template
        // touches it.
        $matched = preg_match('/^SELECT (.*?) FROM /', $dql, $matches);
        $this->assertSame(1, $matched, "Could not find a SELECT clause in DQL: $dql");
        $selectedAliases = array_map('trim', explode(',', $matches[1]));

        foreach (['p', 'country', 'm', 'mt', 'st', 'model', 's', 'restraint', 'currency', 'launch'] as $alias) {
            $this->assertContains($alias, $selectedAliases, "Expected alias '$alias' to be fetch-joined in: $dql");
        }

        // mainImage is never rendered by show.html.twig (unlike the list
        // pages) -- joining it here would only inflate the query and its
        // cached payload for nothing.
        $this->assertNotContains('mi', $selectedAliases);
    }

    public function testFindForShowFiltersByTheGivenId(): void
    {
        $this->stubQueries(0);

        $this->repository->findForShow(42);

        $this->assertStringContainsString('WHERE c.id = :id', $this->mainEntityDql());
    }

    public function testFindForShowCachesTheResult(): void
    {
        $this->stubQueries(0);

        $this->repository->findForShow(1);

        $this->assertSame([['lifetime' => 300, 'isCount' => false]], $this->capturedResultCacheCalls);
    }

    public function testFindAllCoastersInParkCachesForFiveMinutes(): void
    {
        $this->stubQueries(0);

        $this->repository->findAllCoastersInPark(new Park());

        $this->assertSame([['lifetime' => 300, 'isCount' => false]], $this->capturedResultCacheCalls);
    }

    public function testFindAllCoastersInParkWithDetailsFetchJoinsManufacturerSeatingTypeAndMainImage(): void
    {
        $this->stubQueries(0);

        $this->repository->findAllCoastersInParkWithDetails(new Park());

        $dql = $this->mainEntityDql();

        // Regression guard: Park/show.html.twig reads coaster.manufacturer /
        // .seatingType / .mainImage for each coaster, unlike the sidebar
        // version (findAllCoastersInPark()), which only needs status/name.
        $matched = preg_match('/^SELECT (.*?) FROM /', $dql, $matches);
        $this->assertSame(1, $matched, "Could not find a SELECT clause in DQL: $dql");
        $selectedAliases = array_map('trim', explode(',', $matches[1]));

        foreach (['s', 'm', 'st', 'mi'] as $alias) {
            $this->assertContains($alias, $selectedAliases, "Expected alias '$alias' to be fetch-joined in: $dql");
        }
    }

    public function testFindAllCoastersInParkWithDetailsCachesForFiveMinutes(): void
    {
        $this->stubQueries(0);

        $this->repository->findAllCoastersInParkWithDetails(new Park());

        $this->assertSame([['lifetime' => 300, 'isCount' => false]], $this->capturedResultCacheCalls);
    }

    public function testFindForSearchSetsKnpPaginatorCountHintFromASeparateCountQuery(): void
    {
        $this->stubQueries(17);

        $this->repository->findForSearch();

        $this->assertSame(17, $this->capturedHints['knp_paginator.count']);
    }

    public function testFindForSearchCachesBothQueriesWithNoFilters(): void
    {
        $this->stubQueries(0);

        // The default, filterless search (most visits) must be cacheable,
        // same as findForRanking()'s default view.
        $this->repository->findForSearch();

        $this->assertCount(2, $this->capturedResultCacheCalls);
        foreach ($this->capturedResultCacheCalls as $call) {
            $this->assertSame(300, $call['lifetime']);
        }
    }

    public function testFindForSearchDoesNotCacheWhenTheRiddenFilterIsActive(): void
    {
        $this->stubQueries(0);

        $this->repository->findForSearch(['user' => 42, 'ridden' => 'on']);

        $this->assertSame([], $this->capturedResultCacheCalls);
    }

    public function testFindForSearchCachesTheCountButNotTheMainQueryWhenSortingByDistance(): void
    {
        $this->stubQueries(0);

        // The "park has coordinates" WHERE (shared by every visitor sorting
        // by distance) makes the count cacheable, but the main query embeds
        // the visitor's own lat/lng as bound parameters, so caching it would
        // never hit.
        $this->repository->findForSearch(['sortByDistance' => 'on', 'latitude' => 48.85, 'longitude' => 2.35]);

        $this->assertSame([['lifetime' => 300, 'isCount' => true]], $this->capturedResultCacheCalls);
    }
}
