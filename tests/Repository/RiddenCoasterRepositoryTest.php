<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
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
     * @param array<int>                                      $operatingTop100Ids result of the first (ids) query
     * @param array{nb_top100: ?int, nb_top100_operating: ?int, nb_legends: ?int} $aggregate          result of the second (counts) query
     */
    private function stubQueries(array $operatingTop100Ids, array $aggregate): void
    {
        $this->em->method('createQuery')->willReturnCallback(function (string $dql) use ($operatingTop100Ids, $aggregate) {
            $this->capturedDql[] = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();

            if (str_contains($dql, 'nb_top100_operating')) {
                $query->method('getSingleResult')->willReturn($aggregate);
            } else {
                $query->method('getSingleColumnResult')->willReturn($operatingTop100Ids);
            }

            return $query;
        });
    }

    public function testCountTop100ForUserFiltersOperatingCoastersByStatusNameNotId(): void
    {
        $this->stubQueries([11, 22, 33], ['nb_top100' => 5, 'nb_top100_operating' => 3, 'nb_legends' => 1]);

        $this->repository->countTop100ForUser(new User());

        $idsDql = $this->capturedDql[0];

        // Must match the pattern used everywhere else in the codebase
        // (CoasterRepository, ParkRepository): compare Status by name, not a
        // hardcoded id — an id-based comparison silently breaks if ids shift.
        $this->assertStringContainsString('s.code = :operating', $idsDql);
        $this->assertStringNotContainsString('= 1', $idsDql);
    }

    public function testCountTop100ForUserReturnsAggregateCounts(): void
    {
        $this->stubQueries([11, 22, 33], ['nb_top100' => 5, 'nb_top100_operating' => 3, 'nb_legends' => 1]);

        $result = $this->repository->countTop100ForUser(new User());

        $this->assertSame(['nb_top100' => 5, 'nb_top100_operating' => 3, 'nb_legends' => 1], $result);
    }

    public function testCountTop100ForUserDoesNotCrashWhenNoCoasterIsOperating(): void
    {
        // No operating coasters at all — must not build an empty IN(), which
        // Doctrine can't compile.
        $this->stubQueries([], ['nb_top100' => 5, 'nb_top100_operating' => 0, 'nb_legends' => 0]);

        $result = $this->repository->countTop100ForUser(new User());

        $this->assertSame(['nb_top100' => 5, 'nb_top100_operating' => 0, 'nb_legends' => 0], $result);
    }

    public function testCountTop100ForUserReturnsZerosWhenNoTop100CoasterIsRidden(): void
    {
        // SUM() over no matching row is NULL, not 0.
        $this->stubQueries([11, 22, 33], ['nb_top100' => null, 'nb_top100_operating' => null, 'nb_legends' => null]);

        $result = $this->repository->countTop100ForUser(new User());

        $this->assertSame(['nb_top100' => 0, 'nb_top100_operating' => 0, 'nb_legends' => 0], $result);
    }

    public function testGetLatestReviewsFiltersOnHasReviewColumn(): void
    {
        $dql = null;
        $this->em->method('createQuery')->willReturnCallback(function (string $capturedDql) use (&$dql) {
            $dql = $capturedDql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('enableResultCache')->willReturnSelf();
            $query->method('getResult')->willReturn([]);

            return $query;
        });

        $this->repository->getLatestReviews(['en', 'fr'], 3);

        // Must use the generated has_review column (idx_ridden_coaster_has_review_updated_at)
        // rather than `review IS NOT NULL`, which can't use an index on a longtext column.
        $this->assertStringContainsString('r.hasReview = 1', $dql);
        $this->assertStringNotContainsString('r.review', $dql);
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
        $this->assertStringNotContainsString('r.review', $dql);
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

        $coaster = new Coaster();
        (new \ReflectionProperty(Coaster::class, 'id'))->setValue($coaster, 1);

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

        $coaster = new Coaster();
        (new \ReflectionProperty(Coaster::class, 'id'))->setValue($coaster, 1);

        $this->repository->clearRatingStatsCache($coaster);

        $this->assertFalse($pool->hasItem('coaster_rating_stats_1'));
        $this->assertTrue($pool->hasItem('coaster_rating_stats_2'));
    }
}
