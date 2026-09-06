<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ImageRepository::findVisibleForCoaster().
 *
 * Uses a real ManagerRegistry/ClassMetadata pair (rather than the simpler
 * onlyMethods(['getEntityManager']) partial mock) because the method calls
 * $this->createQueryBuilder('i') -- which, unlike
 * $this->getEntityManager()->createQueryBuilder(), goes through
 * ServiceEntityRepository's own resolveRepository()/registry lookup.
 */
class ImageRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ImageRepository $repository;

    private string $capturedDql = '';

    /** @var array<string, mixed> */
    private array $capturedParameters = [];

    private ?int $capturedMaxResults = null;

    protected function setUp(): void
    {
        $this->capturedDql = '';
        $this->capturedParameters = [];
        $this->capturedMaxResults = null;

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );
        $this->em->method('getClassMetadata')->willReturn(new ClassMetadata(Image::class));

        $this->em->method('createQuery')->willReturnCallback(function (string $dql) {
            $this->capturedDql = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setParameters')->willReturnCallback(function ($parameters) use ($query) {
                foreach ($parameters as $parameter) {
                    $this->capturedParameters[$parameter->getName()] = $parameter->getValue();
                }

                return $query;
            });
            $query->method('setMaxResults')->willReturnCallback(function (?int $maxResults) use ($query) {
                $this->capturedMaxResults = $maxResults;

                return $query;
            });
            $query->method('getResult')->willReturn([]);

            return $query;
        });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $this->repository = new ImageRepository($registry);
    }

    public function testFindVisibleForCoasterFiltersOrdersAndLimits(): void
    {
        $coaster = $this->createMock(Coaster::class);

        $this->repository->findVisibleForCoaster($coaster, 8);

        $this->assertStringContainsString('i.enabled = 1', $this->capturedDql);
        $this->assertStringContainsString('i.coaster = :coaster', $this->capturedDql);
        $this->assertStringContainsString('ORDER BY i.likeCounter DESC, i.updatedAt DESC', $this->capturedDql);
        $this->assertSame($coaster, $this->capturedParameters['coaster']);
        $this->assertSame(8, $this->capturedMaxResults);
    }
}
