<?php

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Service\DiscordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CoasterOpenCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var DiscordService
     */
    private $discord;

    /**
     * CoasterOpenCommand constructor.
     * @param EntityManagerInterface $em
     * @param DiscordService $discord
     */
    public function __construct(EntityManagerInterface $em, DiscordService $discord)
    {
        parent::__construct();
        $this->em = $em;
        $this->discord = $discord;
    }

    protected function configure()
    {
        $this
            ->setName('coaster:open')
            ->setDescription('Checks if a coaster opens today and change its status.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $today = new \DateTime();

        if ($today->format('dm') === '0101') {
            $output->writeln('No opening first day of year.');

            return;
        }

        $openingCoasters = $this->em->getRepository('App:Coaster')->findBy(
            [
                'openingDate' => $today,
            ]
        );

        $operatingStatus = $this->em->getRepository('App:Status')->findOneBy(
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
