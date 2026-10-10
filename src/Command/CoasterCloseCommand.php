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

#[AsCommand(
    name: 'coaster:close',
    description: 'Checks if a coaster is closing today and update its status.',
    hidden: false,
)]
class CoasterCloseCommand extends Command
{
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly StatusRepository $statusRepository,
        private readonly EntityManagerInterface $em,
        private readonly ChatterInterface $chatter
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');

        $closingCoasters = $this->coasterRepository->findBy([
            'closingDate' => $today,
            // A date known to the year or the month is stored as its 1st day, not the real one.
            'closingDatePrecision' => DatePrecision::Day,
        ]);

        $closingStatus = $this->statusRepository->findOneBy(['code' => Status::CLOSED_DEFINITELY]);

        /** @var Coaster $coaster */
        foreach ($closingCoasters as $coaster) {
            $coaster->setStatus($closingStatus);
            $this->em->persist($coaster);
            $this->em->flush();

            $this->chatter->send(
                new ChatMessage('We just definitely closed '.$coaster->getName().' at '.$coaster->getPark()->getName().'! 🚫')
                    ->transport('discord_notif')
            );
        }

        return Command::SUCCESS;
    }
}
