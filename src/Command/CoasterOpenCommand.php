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
    name: 'coaster:open',
    description: 'Checks if a coaster is opening today and update its status.',
    hidden: false,
)]
class CoasterOpenCommand extends Command
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
        $today = new \DateTime();

        $openingCoasters = $this->coasterRepository->findBy([
            'openingDate' => $today,
            // A date known to the year or the month is stored as its 1st day, not the real one.
            'openingDatePrecision' => DatePrecision::Day,
        ]);

        $operatingStatus = $this->statusRepository->findOneBy(['code' => Status::OPERATING]);

        /** @var Coaster $coaster */
        foreach ($openingCoasters as $coaster) {
            $coaster->setStatus($operatingStatus);
            $this->em->persist($coaster);
            $this->em->flush();

            $this->chatter->send(
                new ChatMessage('We just opened '.$coaster->getName().' at '.$coaster->getPark()->getName().'! 🎉')
                    ->transport('discord_notif')
            );
        }

        return Command::SUCCESS;
    }
}
