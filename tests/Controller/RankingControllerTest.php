<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\RankingController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RankingControllerTest extends TestCase
{
    /**
     * @param array<string, mixed>  $filters
     * @param ?array<string, mixed> $expected
     */
    #[DataProvider('provideCanonicalFilters')]
    public function testCanonicalFilters(array $filters, ?array $expected): void
    {
        $this->assertSame($expected, RankingController::canonicalFilters($filters));
    }

    /** @return iterable<string, array{array<string, mixed>, ?array<string, mixed>}> */
    public static function provideCanonicalFilters(): iterable
    {
        yield 'full ranking' => [[], []];
        yield 'one country' => [['country' => 18], ['country' => 18]];
        yield 'one continent' => [['continent' => 2], ['continent' => 2]];
        yield 'one manufacturer' => [['manufacturer' => 43], ['manufacturer' => 43]];
        yield 'one seating type' => [['seatingType' => 1], ['seatingType' => 1]];
        yield 'one material type' => [['materialType' => 2], ['materialType' => 2]];
        yield 'two filters' => [['country' => 18, 'manufacturer' => 43], null];
        yield 'a filter that is not a page of its own' => [['name' => 'steel'], null];
        yield 'riders own filter' => [['ridden' => 'on'], null];
    }
}
