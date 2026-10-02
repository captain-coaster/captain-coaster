<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\FilterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\Cache\CacheInterface;

class FilterServiceTest extends TestCase
{
    private FilterService $service;

    protected function setUp(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn([]);

        $this->service = new FilterService(
            $this->createStub(EntityManagerInterface::class),
            $cache,
            $this->createStub(FormFactoryInterface::class),
        );
    }

    /** "Load more" links and a reloaded URL carry the filter without its rider. */
    #[DataProvider('riderFilters')]
    public function testRiderFilterDefaultsToTheSignedInRider(string $filter): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(7);

        self::assertSame(
            [$filter => 'on', 'user' => 7],
            $this->service->validateAndAuthorize([$filter => 'on'], 'ranking', $user),
        );
    }

    #[DataProvider('riderFilters')]
    public function testRiderFilterIsDroppedForAVisitor(string $filter): void
    {
        self::assertSame(
            ['status' => 'on'],
            $this->service->validateAndAuthorize([$filter => 'on', 'status' => 'on'], 'ranking', null),
        );
    }

    public function testRiderIsNotAddedWithoutARiderFilter(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(7);

        self::assertSame(['status' => 'on'], $this->service->validateAndAuthorize(['status' => 'on'], 'ranking', $user));
    }

    /** @return iterable<string, array{string}> */
    public static function riderFilters(): iterable
    {
        yield 'ridden' => ['ridden'];
        yield 'not ridden' => ['notridden'];
    }
}
