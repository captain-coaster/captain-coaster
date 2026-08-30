<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Uses real QueryBuilder instances (built against a mocked EntityManager) so the
 * generated DQL is genuine — this catches the OR-across-joins pattern creeping
 * back in, which a fully-mocked QueryBuilder would miss.
 */
class UserRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private UserRepository&MockObject $repository;

    /** @var list<string> */
    private array $capturedDql = [];

    protected function setUp(): void
    {
        $this->capturedDql = [];

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );

        $this->repository = $this->getMockBuilder(UserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $this->repository->method('getEntityManager')->willReturn($this->em);
    }

    public function testGetUsersWithRecentRatingOrTopUpdateRunsTwoSeparateSingleJoinQueries(): void
    {
        $ratingUser = $this->userWithId(1);
        $topUser = $this->userWithId(2);

        $this->em->method('createQuery')->willReturnCallback(function (string $dql) use ($ratingUser, $topUser) {
            $this->capturedDql[] = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn(str_contains($dql, 'u.ratings') ? [$ratingUser] : [$topUser]);

            return $query;
        });

        $result = $this->repository->getUsersWithRecentRatingOrTopUpdate();

        $this->assertCount(2, $this->capturedDql, 'Expected two separate queries, not one OR-across-joins query');
        foreach ($this->capturedDql as $dql) {
            // Each query must join exactly one association -- joining both in a single
            // query is what forces the OR-across-joins full-join blowup this fixes.
            $this->assertMatchesRegularExpression('/INNER JOIN u\.(ratings|tops)/', $dql);
            $this->assertStringNotContainsString(' OR ', $dql);
        }
        $this->assertSame([$ratingUser, $topUser], $result);
    }

    public function testGetUsersWithRecentRatingOrTopUpdateDeduplicatesUsersInBothResultSets(): void
    {
        $user = $this->userWithId(42);

        $this->em->method('createQuery')->willReturnCallback(function () use ($user) {
            $query = $this->createMock(Query::class);
            $query->method('setParameters')->willReturnSelf();
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('getResult')->willReturn([$user]);

            return $query;
        });

        $result = $this->repository->getUsersWithRecentRatingOrTopUpdate();

        $this->assertCount(1, $result, 'A user with both a recent rating and a recent top must not be duplicated');
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $property = new \ReflectionProperty(User::class, 'id');
        $property->setAccessible(true);
        $property->setValue($user, $id);

        return $user;
    }
}
