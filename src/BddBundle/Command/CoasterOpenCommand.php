<?php

namespace BddBundle\Command;

use BddBundle\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * CoasterOpenCommand constructor.
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        parent::__construct();
        $this->em = $em;
        $this->logger = $logger;
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

        $openingCoasters = $this->em->getRepository('BddBundle:Coaster')->findBy(
            [
                'openingDate' => $today,
            ]
        );

        $operatingStatus = $this->em->getRepository('BddBundle:Status')->findOneBy(
            ['name' => Status::OPERATING]
        );

        foreach ($openingCoasters as $coaster) {
            $coaster->setStatus($operatingStatus);
            $this->em->persist($coaster);
            $this->em->flush();

            $this->logger->critical('We just opened '.$coaster->getName().' at '.$coaster->getPark()->getName().'! 🎉');
        }
    }

}
