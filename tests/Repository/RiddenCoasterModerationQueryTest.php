<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RiddenCoasterRepository::findPendingAnalysis().
 *
 * This project has no KernelTestCase/database-backed test infrastructure
 * (see tests/Config/MonologConfigTest.php and the single existing
 * repository test, CoasterSummaryRepositoryTest, both plain TestCase +
 * mocks; CI runs `vendor/bin/phpunit` with no database service). So,
 * matching that convention, this verifies the DQL/parameters the method
 * builds rather than executing against a real database: it lets the real
 * method run against a real QueryBuilder (backed by a mocked
 * EntityManagerInterface), stubbing out only the final getQuery() step.
 */
class RiddenCoasterModerationQueryTest extends TestCase
{
    private function createRepositoryWithQueryBuilder(QueryBuilder $queryBuilder): RiddenCoasterRepository&MockObject
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $repository = $this->getMockBuilder(RiddenCoasterRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $repository->method('getEntityManager')->willReturn($entityManager);

        return $repository;
    }

    /** @param array<int, RiddenCoaster> $result */
    private function createQueryBuilder(array $result): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($result);

        $entityManagerForQb = $this->createMock(EntityManagerInterface::class);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$entityManagerForQb])
            ->onlyMethods(['getQuery'])
            ->getMock();
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }

    public function testFindPendingAnalysisFindsHiddenUnanalyzedReviewsWithText(): void
    {
        $expected = [$this->createMock(RiddenCoaster::class)];
        $queryBuilder = $this->createQueryBuilder($expected);
        $repository = $this->createRepositoryWithQueryBuilder($queryBuilder);

        $result = $repository->findPendingAnalysis(100);

        $dql = $queryBuilder->getDQL();
        $this->assertStringContainsString('r.reviewEnabled = 0', $dql);
        $this->assertStringContainsString('r.analyzedAt IS NULL', $dql);
        $this->assertStringContainsString('r.review IS NOT NULL', $dql);
        $this->assertStringContainsString('TRIM(r.review) != \'\'', $dql);
        $this->assertSame($expected, $result);
    }

    public function testFindPendingAnalysisAppliesLimitAndOrdersById(): void
    {
        $queryBuilder = $this->createQueryBuilder([]);
        $repository = $this->createRepositoryWithQueryBuilder($queryBuilder);

        $repository->findPendingAnalysis(42);

        $this->assertSame(42, $queryBuilder->getMaxResults());
        $this->assertStringContainsString('ORDER BY r.id ASC', $queryBuilder->getDQL());
    }
}
