<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Coaster;
use PHPUnit\Framework\TestCase;

class CoasterBestRankTest extends TestCase
{
    public function testFirstRankIsTheBest(): void
    {
        $at = new \DateTime('2026-09-01');
        $coaster = (new Coaster())->recordBestRank(12, $at);

        $this->assertSame(12, $coaster->getBestRank());
        $this->assertSame($at, $coaster->getBestRankAt());
    }

    public function testBetterRankReplacesTheBest(): void
    {
        $october = new \DateTime('2026-10-01');
        $coaster = (new Coaster())
            ->recordBestRank(12, new \DateTime('2026-09-01'))
            ->recordBestRank(8, $october);

        $this->assertSame(8, $coaster->getBestRank());
        $this->assertSame($october, $coaster->getBestRankAt());
    }

    public function testEqualOrWorseRankKeepsTheFirstTimeTheBestWasReached(): void
    {
        $september = new \DateTime('2026-09-01');
        $coaster = (new Coaster())
            ->recordBestRank(8, $september)
            ->recordBestRank(8, new \DateTime('2026-10-01'))
            ->recordBestRank(15, new \DateTime('2026-11-01'));

        $this->assertSame(8, $coaster->getBestRank());
        $this->assertSame($september, $coaster->getBestRankAt());
    }
}
