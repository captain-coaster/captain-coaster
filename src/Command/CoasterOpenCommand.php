<?php

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Service\DiscordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CoasterOpenCommand extends Command
{
    public $repository;
    protected static $defaultName = 'coaster:open';
    /**
     * CoasterOpenCommand constructor.
     * @param EntityManagerInterface $em
     * @param DiscordService $discord
     */
    public function __construct(private readonly EntityManagerInterface $em, private readonly DiscordService $discord, private readonly \App\Repository\CoasterRepository $coasterRepository, private readonly \App\Repository\StatusRepository $statusRepository)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Checks if a coaster opens today and change its status.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $today = new \DateTime();

        if ($today->format('dm') === '0101') {
            $output->writeln('No opening first day of year.');

            return;
        }

        $openingCoasters = $this->repository->findBy(
            [
                'openingDate' => $today,
            ]
        );

        $operatingStatus = $this->repository->findOneBy(
            ['name' => Status::OPERATING]
        );

        /** @var Coaster $coaster */
        foreach ($openingCoasters as $coaster) {
            $coaster->setStatus($operatingStatus);
            $this->em->persist($coaster);
            $this->em->flush();

            $this->discord->notify('We just opened '.$coaster->getName().' at '.$coaster->getPark()->getName().'! 🎉');
        }
    }
}
