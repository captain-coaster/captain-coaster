<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Repository\ContinentRepository;
use App\Repository\CoasterRepository;
use App\Service\RecordsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecordsServiceTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private ContinentRepository&MockObject $continentRepository;
    private RecordsService $service;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->continentRepository = $this->createMock(ContinentRepository::class);
        $this->service = new RecordsService($this->coasterRepository, $this->continentRepository);
    }

    private function coaster(int $height): Coaster
    {
        $coaster = new Coaster();
        $coaster->setHeight($height);

        return $coaster;
    }

    public function testDistinctValuesGetSequentialRanks(): void
    {
        $this->coasterRepository->method('findTopByMetric')
            ->willReturn([$this->coaster(50), $this->coaster(40), $this->coaster(30)]);

        $records = $this->service->getRecords();

        $ranks = array_map(static fn (array $entry) => $entry['rank'], $records[0]['coasters']);
        $this->assertSame([1, 2, 3], $ranks);
    }

    public function testTieAtSecondPlacePushesThirdRowOutOfPodium(): void
    {
        $this->coasterRepository->method('findTopByMetric')
            ->willReturn([$this->coaster(50), $this->coaster(40), $this->coaster(40), $this->coaster(30)]);

        $records = $this->service->getRecords();

        $ranks = array_map(static fn (array $entry) => $entry['rank'], $records[0]['coasters']);
        $this->assertSame([1, 2, 2], $ranks);
    }

    public function testTieAtThirdPlaceKeepsBothTiedRows(): void
    {
        $this->coasterRepository->method('findTopByMetric')
            ->willReturn([$this->coaster(50), $this->coaster(40), $this->coaster(30), $this->coaster(30)]);

        $records = $this->service->getRecords();

        $ranks = array_map(static fn (array $entry) => $entry['rank'], $records[0]['coasters']);
        $this->assertSame([1, 2, 3, 3], $ranks);
    }

    public function testTiesExhaustingFetchCapAreCappedNotErrored(): void
    {
        $this->coasterRepository->method('findTopByMetric')
            ->willReturn([$this->coaster(50), $this->coaster(50), $this->coaster(50), $this->coaster(50), $this->coaster(50)]);

        $records = $this->service->getRecords();

        $ranks = array_map(static fn (array $entry) => $entry['rank'], $records[0]['coasters']);
        $this->assertSame([1, 1, 1, 1, 1], $ranks);
        $this->assertCount(5, $records[0]['coasters']);
    }
}
