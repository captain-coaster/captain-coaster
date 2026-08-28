<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RiddenCoasterRepository::countTop100ForUser().
 *
 * Uses real QueryBuilder instances (built against a mocked EntityManager) so the
 * generated DQL is genuine, only the final Query execution is stubbed — this
 * catches DQL-building regressions (e.g. a hardcoded Status id creeping back in)
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
     * @param array{nb_top100: int, nb_top100_operating: int} $aggregate          result of the second (counts) query
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
        $this->stubQueries([11, 22, 33], ['nb_top100' => 5, 'nb_top100_operating' => 3]);

        $this->repository->countTop100ForUser(new User());

        $idsDql = $this->capturedDql[0];

        // Must match the pattern used everywhere else in the codebase
        // (CoasterRepository, ParkRepository): compare Status by name, not a
        // hardcoded id — an id-based comparison silently breaks if ids shift.
        $this->assertStringContainsString('s.name = :operating', $idsDql);
        $this->assertStringNotContainsString('= 1', $idsDql);
    }

    public function testCountTop100ForUserReturnsAggregateCounts(): void
    {
        $this->stubQueries([11, 22, 33], ['nb_top100' => 5, 'nb_top100_operating' => 3]);

        $result = $this->repository->countTop100ForUser(new User());

        $this->assertSame(['nb_top100' => 5, 'nb_top100_operating' => 3], $result);
    }

    public function testCountTop100ForUserDoesNotCrashWhenNoCoasterIsOperating(): void
    {
        // No operating coasters at all — must not build an empty IN(), which
        // Doctrine can't compile.
        $this->stubQueries([], ['nb_top100' => 5, 'nb_top100_operating' => 0]);

        $result = $this->repository->countTop100ForUser(new User());

        $this->assertSame(['nb_top100' => 5, 'nb_top100_operating' => 0], $result);
    }
}
