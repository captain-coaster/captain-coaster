<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Tests\EntityId;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for RiddenCoasterRepository.
 *
 * Uses real QueryBuilder instances (built against a mocked EntityManager) so the
 * generated DQL is genuine, only the final Query execution is stubbed — this
 * catches DQL-building regressions (e.g. a hardcoded Status id creeping back in,
 * or a filter reverting to the un-indexable `review IS NOT NULL` predicate)
 * that a fully-mocked QueryBuilder would miss.
 */
class RiddenCoasterRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RiddenCoasterRepository&MockObject $repository;

    /** @var list<string> */
    private array $capturedDql = [];

    protected function setUp(): void
    {
        $this->capturedDql = [];

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );

        $this->repository = $this->getMockBuilder(RiddenCoasterRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $this->repository->method('getEntityManager')->willReturn($this->em);
    }

    /**
     * @param array<int> $top100Ids result of the first (ids) query
     */
    private function stubQueries(array $top100Ids, int $ridden): void
    {
        $this->em->method('createQuery')->willReturnCallback(function (string $dql) use ($top100Ids, $ridden) {
            $this->capturedDql[] = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getSingleColumnResult')->willReturn($top100Ids);
            $query->method('getSingleScalarResult')->willReturn((string) $ridden);

            return $query;
        });
    }

    public function testCountTop100ForUserLeavesOnlyGoneCoastersOut(): void
    {
        $this->stubQueries([11, 22, 33], 3);

        $this->repository->countTop100ForUser(new User());

        // A temporarily closed coaster keeps its place: only the gone statuses are excluded, by code.
        $this->assertStringContainsString('s.code NOT IN (:gone)', $this->capturedDql[0]);
        $this->assertStringNotContainsString(':operating', $this->capturedDql[0]);
    }

    public function testCountTop100ForUserReturnsTheRiddenCount(): void
    {
        $this->stubQueries([11, 22, 33], 3);

        $this->assertSame(3, $this->repository->countTop100ForUser(new User()));
    }

    public function testCountTop100ForUserReturnsZeroWithoutRankedCoaster(): void
    {
        // No IN() is built from an empty list, which Doctrine can't compile.
        $this->stubQueries([], 5);

        $this->assertSame(0, $this->repository->countTop100ForUser(new User()));
        $this->assertCount(1, $this->capturedDql);
    }

    public function testFindAllReviewsFiltersOnHasReviewColumn(): void
    {
        $dql = null;
        $this->em->method('createQuery')->willReturnCallback(function (string $capturedDql) use (&$dql) {
            $dql = $capturedDql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn([]);

            return $query;
        });

        $this->repository->findAllReviews(['en', 'fr'], 11);

        $this->assertStringContainsString('r.hasReview = 1', $dql);
        $this->assertDoesNotMatchRegularExpression('/\br\.review\b/', $dql);
        $this->assertStringContainsString('r.reviewEnabled = 1', $dql);
    }

    public function testFindAllReviewsFetchJoinsCoasterAndPark(): void
    {
        $dql = null;
        $this->em->method('createQuery')->willReturnCallback(function (string $capturedDql) use (&$dql) {
            $dql = $capturedDql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn([]);

            return $query;
        });

        $this->repository->findAllReviews(['en'], 11);

        // Regression guard: Review/list.html.twig reads review.coaster(.park)
        // directly for every review -- coaster/park are plain LAZY
        // ManyToOne, so without this, up to 20 extra queries fire per page
        // (10 reviews x coaster + park).
        $this->assertStringContainsString('INNER JOIN', $dql);
        $this->assertStringContainsString('r.coaster', $dql);
        $this->assertStringContainsString('c.park', $dql);
    }

    public function testFindAllReviewsUsesLimitFromCaller(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameters')->willReturnSelf();
        $query->method('setFirstResult')->willReturnSelf();
        $query->expects($this->once())->method('setMaxResults')->with(11)->willReturnSelf();
        $query->method('getResult')->willReturn([]);

        // Exactly one query built -- $limit is the caller's responsibility
        // (count+1, see ReviewController::listAction()): "load more"
        // re-fetches from the top with a larger limit instead of a separate
        // count query plus paging deeper into the result set.
        $this->em->expects($this->once())->method('createQuery')->willReturn($query);

        $this->repository->findAllReviews(['en'], 11);
    }

    public function testGetRatingStatsForCoasterCachesUnderAPerCoasterId(): void
    {
        $capturedResultCacheCalls = [];
        $this->em->method('createQuery')->willReturnCallback(function (string $dql) use (&$capturedResultCacheCalls) {
            $query = $this->createMock(Query::class);
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('setParameters')->willReturnSelf();
            $query->method('enableResultCache')->willReturnCallback(function (?int $lifetime, ?string $id) use ($query, &$capturedResultCacheCalls) {
                $capturedResultCacheCalls[] = [$lifetime, $id];

                return $query;
            });
            $query->method('getResult')->willReturn([]);

            return $query;
        });

        $coaster = EntityId::set(new Coaster(), 1);

        $this->repository->getRatingStatsForCoaster($coaster);

        $this->assertSame([[3600, 'coaster_rating_stats_1']], $capturedResultCacheCalls);
    }

    public function testClearRatingStatsCacheDeletesTheEntryTheQueryIsCachedUnder(): void
    {
        $pool = new ArrayAdapter();
        $pool->save($pool->getItem('coaster_rating_stats_1')->set([]));
        $pool->save($pool->getItem('coaster_rating_stats_2')->set([]));
        $configuration = new Configuration();
        $configuration->setResultCache($pool);
        $this->em->method('getConfiguration')->willReturn($configuration);

        $coaster = EntityId::set(new Coaster(), 1);

        $this->repository->clearRatingStatsCache($coaster);

        $this->assertFalse($pool->hasItem('coaster_rating_stats_1'));
        $this->assertTrue($pool->hasItem('coaster_rating_stats_2'));
    }
}
