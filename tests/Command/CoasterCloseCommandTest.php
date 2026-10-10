<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CoasterCloseCommand;
use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\Status;
use App\Enum\DatePrecision;
use App\Repository\CoasterRepository;
use App\Repository\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Notifier\ChatterInterface;

class CoasterCloseCommandTest extends TestCase
{
    public function testClosesTheCoastersWhoseClosingDayIsToday(): void
    {
        $coaster = $this->createMock(Coaster::class);
        $coaster->method('getPark')->willReturn($this->createStub(Park::class));
        $closed = $this->createStub(Status::class);

        // The column is date_immutable: Doctrine refuses a mutable DateTime as criteria.
        $coasters = $this->createMock(CoasterRepository::class);
        $coasters->expects($this->once())->method('findBy')->with($this->callback(
            static fn (array $criteria): bool => $criteria['closingDate'] instanceof \DateTimeImmutable
                && $criteria['closingDate']->format('Y-m-d H:i:s') === date('Y-m-d').' 00:00:00'
                && DatePrecision::Day === $criteria['closingDatePrecision']
        ))->willReturn([$coaster]);
        $statuses = $this->createStub(StatusRepository::class);
        $statuses->method('findOneBy')->willReturn($closed);
        $chatter = $this->createMock(ChatterInterface::class);

        $coaster->expects($this->once())->method('setStatus')->with($closed);
        $chatter->expects($this->once())->method('send');

        $command = new CoasterCloseCommand($coasters, $statuses, $this->createStub(EntityManagerInterface::class), $chatter);

        $this->assertSame(0, new CommandTester($command)->execute([]));
    }
}
