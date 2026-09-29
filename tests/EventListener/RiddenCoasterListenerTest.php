<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\EventListener\RiddenCoasterListener;
use App\Repository\RiddenCoasterRepository;
use PHPUnit\Framework\TestCase;

class RiddenCoasterListenerTest extends TestCase
{
    public function testClearsTheCoasterRatingStats(): void
    {
        $coaster = new Coaster();
        $riddenCoaster = new RiddenCoaster()->setCoaster($coaster);

        $repository = $this->createMock(RiddenCoasterRepository::class);
        $repository->expects($this->once())->method('clearRatingStatsCache')->with($coaster);

        new RiddenCoasterListener($repository)->clearCoasterCache($riddenCoaster);
    }
}
