<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Repository\CoasterRepository;
use App\Repository\StatusRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class CoasterCloseCommand extends Command
{
    protected static $defaultName = 'coaster:close';

    public function __construct(
        private readonly CoasterRepository      $coasterRepository,
        private readonly StatusRepository       $statusRepository,
        private readonly EntityManagerInterface $em,
        private readonly ChatterInterface       $chatter
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Checks if a coaster opens today and change its status.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new DateTime();

        $closingCoasters = $this->coasterRepository->findBy([
            'closingDate' => $today,
        ]);

        $closingStatus = $this->statusRepository->findOneBy(['name' => Status::CLOSED_DEFINITELY]);

        /** @var Coaster $coaster */
        foreach ($closingCoasters as $coaster) {
            $coaster->setStatus($closingStatus);
            $this->em->persist($coaster);
            $this->em->flush();

            $this->chatter->send(
                (new ChatMessage('We just definitely closed ' . $coaster->getName() . ' at ' . $coaster->getPark()->getName() . '! 🚫'))
                    ->transport('discord_notif')
            );
        }

        return 0;
    }
}
