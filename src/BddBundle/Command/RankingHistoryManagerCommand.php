<?php

namespace BddBundle\Command;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\Ranking;
use BddBundle\Entity\RankingHistory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RankingHistoryManagerCommand extends ContainerAwareCommand
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
     * RankingHistoryManagerCommand constructor.
     * @param EntityManagerInterface $em
     * @param LoggerInterface        $logger
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
            ->setName('app:ranking:history:add')
            ->setDescription('Saves current ranking stats.')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Start ranking history.');

        $currentRanking = $this->em->getRepository('BddBundle:Ranking')->findCurrent();
        if (!$currentRanking instanceof Ranking) {
            $this->logger->critical('Cannot find current ranking!');
        }

        /** @var Coaster $coaster */
        foreach ($this->em->getRepository('BddBundle:Coaster')->findByRanking()->getResult() as $coaster) {
            $rankedCoaster = new RankingHistory();
            $rankedCoaster->setCoaster($coaster);
            $rankedCoaster->setRank($coaster->getRank());
            $rankedCoaster->setScore($coaster->getScore());
            $rankedCoaster->setAverageRating($coaster->getAverageRating());
            $rankedCoaster->setAverageTopRank($coaster->getAverageTopRank());
            $rankedCoaster->setTotalRatings($coaster->getTotalRatings());
            $rankedCoaster->setTotalTopsIn($coaster->getTotalTopsIn());
            $rankedCoaster->setValidDuels($coaster->getValidDuels());

            $rankedCoaster->setRanking($currentRanking);

            $this->em->persist($rankedCoaster);
            $output->writeln('Adding '.$coaster->getName());
        }

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->logger->critical('Could not save history: '.$e->getMessage());
        }

        $output->writeln('All ranked coasters saved.');
    }
}
