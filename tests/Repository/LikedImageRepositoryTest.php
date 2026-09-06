<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\User;
use App\Repository\LikedImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LikedImageRepository::findUserLikesForCoaster().
 */
class LikedImageRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LikedImageRepository&MockObject $repository;

    /** @var array<string, mixed> */
    private array $capturedParameters = [];

    private string $capturedDql = '';

    protected function setUp(): void
    {
        $this->capturedParameters = [];
        $this->capturedDql = '';

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );
        $this->em->method('createQuery')->willReturnCallback(function (string $dql) {
            $this->capturedDql = $dql;

            $query = $this->createMock(Query::class);
            // QueryBuilder::getQuery() hands parameters over via a single
            // setParameters(ArrayCollection<Parameter>) call, not individual
            // setParameter() calls -- unpack it to inspect each one.
            $query->method('setParameters')->willReturnCallback(function ($parameters) use ($query) {
                foreach ($parameters as $parameter) {
                    $this->capturedParameters[$parameter->getName()] = $parameter->getValue();
                }

                return $query;
            });

            return $query;
        });

        $this->repository = $this->getMockBuilder(LikedImageRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $this->repository->method('getEntityManager')->willReturn($this->em);
    }

    public function testScopesTheQueryToTheGivenUserAndCoaster(): void
    {
        $user = $this->createMock(User::class);
        $coaster = $this->createMock(Coaster::class);

        $this->repository->findUserLikesForCoaster($user, $coaster);

        $this->assertStringContainsString('li.user = :user', $this->capturedDql);
        $this->assertStringContainsString('i.coaster = :coaster', $this->capturedDql);
        $this->assertSame($user, $this->capturedParameters['user']);
        $this->assertSame($coaster, $this->capturedParameters['coaster']);
    }
}
