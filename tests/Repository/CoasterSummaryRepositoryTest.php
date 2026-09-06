<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\CoasterSummaryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CoasterSummaryRepository::findByCoasterAndLanguage() and
 * clearCacheFor().
 *
 * Uses a real ManagerRegistry/ClassMetadata pair (rather than the simpler
 * onlyMethods(['getEntityManager']) partial mock) because the method calls
 * $this->createQueryBuilder('cs') -- which, unlike
 * $this->getEntityManager()->createQueryBuilder(), goes through
 * ServiceEntityRepository's own resolveRepository()/registry lookup.
 */
class CoasterSummaryRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CoasterSummaryRepository $repository;

    private string $capturedDql = '';

    /** @var array<string, mixed> */
    private array $capturedParameters = [];

    /** @var list<array{lifetime: ?int, id: ?string}> */
    private array $capturedResultCacheCalls = [];

    protected function setUp(): void
    {
        $this->capturedDql = '';
        $this->capturedParameters = [];
        $this->capturedResultCacheCalls = [];

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQueryBuilder')->willReturnCallback(
            fn () => new QueryBuilder($this->em)
        );
        $this->em->method('getClassMetadata')->willReturn(new ClassMetadata(CoasterSummary::class));

        $this->em->method('createQuery')->willReturnCallback(function (string $dql) {
            $this->capturedDql = $dql;

            $query = $this->createMock(Query::class);
            $query->method('setFirstResult')->willReturnSelf();
            $query->method('setMaxResults')->willReturnSelf();
            $query->method('setParameters')->willReturnCallback(function ($parameters) use ($query) {
                foreach ($parameters as $parameter) {
                    $this->capturedParameters[$parameter->getName()] = $parameter->getValue();
                }

                return $query;
            });
            $query->method('enableResultCache')->willReturnCallback(function (?int $lifetime, ?string $id) use ($query) {
                $this->capturedResultCacheCalls[] = ['lifetime' => $lifetime, 'id' => $id];

                return $query;
            });
            $query->method('getOneOrNullResult')->willReturn(null);

            return $query;
        });

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);

        $this->repository = new CoasterSummaryRepository($registry);
    }

    private function coasterWithId(int $id): Coaster
    {
        $coaster = new Coaster();
        (new \ReflectionProperty(Coaster::class, 'id'))->setValue($coaster, $id);

        return $coaster;
    }

    public function testFindByCoasterAndLanguageFiltersByBoth(): void
    {
        $coaster = $this->coasterWithId(42);

        $this->repository->findByCoasterAndLanguage($coaster, 'fr');

        $this->assertStringContainsString('cs.coaster = :coaster', $this->capturedDql);
        $this->assertStringContainsString('cs.language = :language', $this->capturedDql);
        $this->assertSame($coaster, $this->capturedParameters['coaster']);
        $this->assertSame('fr', $this->capturedParameters['language']);
    }

    public function testFindByCoasterAndLanguageCachesUnderAPerCoasterLanguageId(): void
    {
        $coaster = $this->coasterWithId(42);

        $this->repository->findByCoasterAndLanguage($coaster, 'fr');

        $this->assertSame([['lifetime' => 604800, 'id' => 'coaster_summary_42_fr']], $this->capturedResultCacheCalls);
    }

    public function testClearCacheForDeletesTheMatchingCacheId(): void
    {
        $deletedIds = [];
        $pool = $this->createMock(\Psr\Cache\CacheItemPoolInterface::class);
        $pool->method('deleteItem')->willReturnCallback(static function (string $id) use (&$deletedIds) {
            $deletedIds[] = $id;

            return true;
        });

        $configuration = $this->createMock(\Doctrine\ORM\Configuration::class);
        $configuration->method('getResultCache')->willReturn($pool);
        $this->em->method('getConfiguration')->willReturn($configuration);

        $coaster = $this->coasterWithId(42);
        $this->repository->clearCacheFor($coaster, 'fr');

        $this->assertSame(['coaster_summary_42_fr'], $deletedIds);
    }
}
