<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Enum\DatePrecision;
use App\Repository\CoasterRepository;
use App\Repository\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

#[AsCommand(name: 'coaster:status', description: 'Opens and closes the coasters whose opening or closing day is today.')]
class CoasterStatusCommand extends Command
{
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly StatusRepository $statusRepository,
        private readonly EntityManagerInterface $em,
        private readonly ChatterInterface $chatter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->update('opening', Status::OPERATING, '🎉 Opened today: %s at %s');
        $this->update('closing', Status::CLOSED_DEFINITELY, '🚫 Closed for good today: %s at %s');

        return Command::SUCCESS;
    }

    private function update(string $event, string $statusCode, string $message): void
    {
        $coasters = $this->coasterRepository->findBy([
            $event.'Date' => new \DateTimeImmutable('today'),
            // A date known to the year or the month is stored as its 1st day, not the real one.
            $event.'DatePrecision' => DatePrecision::Day,
        ]);
        $status = $this->statusRepository->findOneBy(['code' => $statusCode]);

        /** @var Coaster $coaster */
        foreach ($coasters as $coaster) {
            $coaster->setStatus($status);
            $this->em->flush();

            $this->chatter->send(
                new ChatMessage(\sprintf($message, $coaster->getName(), $coaster->getPark()->getName()))->transport('discord_log')
            );
        }
    }
}
