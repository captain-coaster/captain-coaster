<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CoasterStatusCommand;
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
use Symfony\Component\Notifier\Message\ChatMessage;

class CoasterStatusCommandTest extends TestCase
{
    public function testOpensAndClosesTheCoastersWhoseDayIsToday(): void
    {
        $park = $this->createStub(Park::class);
        $park->method('getName')->willReturn('Park');
        $opening = $this->createMock(Coaster::class);
        $opening->method('getName')->willReturn('New');
        $opening->method('getPark')->willReturn($park);
        $closing = $this->createMock(Coaster::class);
        $closing->method('getName')->willReturn('Old');
        $closing->method('getPark')->willReturn($park);
        $operating = $this->createStub(Status::class);
        $closed = $this->createStub(Status::class);

        // The columns are date_immutable: Doctrine refuses a mutable DateTime as criteria.
        $today = static fn (mixed $date): bool => $date instanceof \DateTimeImmutable
            && $date->format('Y-m-d H:i:s') === date('Y-m-d').' 00:00:00';
        $coasters = $this->createStub(CoasterRepository::class);
        $coasters->method('findBy')->willReturnCallback(static fn (array $criteria): array => match (true) {
            isset($criteria['openingDate']) && $today($criteria['openingDate']) && DatePrecision::Day === $criteria['openingDatePrecision'] => [$opening],
            isset($criteria['closingDate']) && $today($criteria['closingDate']) && DatePrecision::Day === $criteria['closingDatePrecision'] => [$closing],
            default => [],
        });
        $statuses = $this->createStub(StatusRepository::class);
        $statuses->method('findOneBy')->willReturnCallback(static fn (array $criteria): Status => Status::OPERATING === $criteria['code'] ? $operating : $closed);

        $opening->expects($this->once())->method('setStatus')->with($operating);
        $closing->expects($this->once())->method('setStatus')->with($closed);

        $sent = [];
        $chatter = $this->createStub(ChatterInterface::class);
        $chatter->method('send')->willReturnCallback(static function (ChatMessage $message) use (&$sent): null {
            $sent[] = [$message->getTransport(), $message->getSubject()];

            return null;
        });

        $command = new CoasterStatusCommand($coasters, $statuses, $this->createStub(EntityManagerInterface::class), $chatter);

        $this->assertSame(0, new CommandTester($command)->execute([]));
        $this->assertSame([
            ['discord_log', '🎉 Opened today: New at Park'],
            ['discord_log', '🚫 Closed for good today: Old at Park'],
        ], $sent);
    }
}
