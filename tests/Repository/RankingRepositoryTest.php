<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Ranking;
use App\Repository\RankingRepository;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Unit tests for RankingRepository.
 *
 * findCurrent()/findPrevious() are cached via enableResultCache() with an
 * explicit id -- not a generic CacheInterface -- specifically so a cache hit
 * still returns a Doctrine-managed entity. RankingHistoryManagerCommand uses
 * the returned Ranking as an association target ($rankingHistory->setRanking(...))
 * and would throw on flush() if it received a detached copy instead.
 */
class RankingRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RankingRepository&MockObject $repository;

    /** @var list<array{lifetime: ?int, id: ?string}> */
    private array $capturedResultCacheCalls = [];

    protected function setUp(): void
    {
        $this->capturedResultCacheCalls = [];

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );

        $this->repository = $this->getMockBuilder(RankingRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $this->repository->method('getEntityManager')->willReturn($this->em);
    }

    /** @return Query<int, Ranking>&MockObject */
    private function stubQuery(?Ranking $result, bool $throwsNoResult = false): Query&MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameters')->willReturnSelf();
        $query->method('setFirstResult')->willReturnSelf();
        $query->method('setMaxResults')->willReturnSelf();
        $query->method('enableResultCache')->willReturnCallback(function (?int $lifetime, ?string $id) use ($query) {
            $this->capturedResultCacheCalls[] = ['lifetime' => $lifetime, 'id' => $id];

            return $query;
        });

        if ($throwsNoResult) {
            $query->method('getSingleResult')->willThrowException(new NoResultException());
        } else {
            $query->method('getSingleResult')->willReturn($result);
        }

        $this->em->method('createQuery')->willReturn($query);

        return $query;
    }

    public function testFindCurrentCachesUnderAFixedIdWithALongTtl(): void
    {
        $ranking = new Ranking();
        $this->stubQuery($ranking);

        $result = $this->repository->findCurrent();

        $this->assertSame($ranking, $result);
        $this->assertSame([['lifetime' => 604800, 'id' => 'ranking_current']], $this->capturedResultCacheCalls);
    }

    public function testFindPreviousCachesUnderItsOwnFixedId(): void
    {
        $ranking = new Ranking();
        $this->stubQuery($ranking);

        $result = $this->repository->findPrevious();

        $this->assertSame($ranking, $result);
        $this->assertSame([['lifetime' => 604800, 'id' => 'ranking_previous']], $this->capturedResultCacheCalls);
    }

    public function testFindCurrentReturnsNullWhenNoRankingExistsYet(): void
    {
        $this->stubQuery(null, throwsNoResult: true);

        $this->assertNull($this->repository->findCurrent());
    }

    public function testClearCacheDeletesBothFixedIdsFromTheResultCachePool(): void
    {
        $deletedIds = [];
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('deleteItem')->willReturnCallback(static function (string $id) use (&$deletedIds) {
            $deletedIds[] = $id;

            return true;
        });

        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getResultCache')->willReturn($pool);
        $this->em->method('getConfiguration')->willReturn($configuration);

        $this->repository->clearCache();

        $this->assertSame(['ranking_current', 'ranking_previous'], $deletedIds);
    }
}
