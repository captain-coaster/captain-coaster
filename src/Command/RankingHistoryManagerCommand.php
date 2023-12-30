<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\Ranking;
use App\Entity\RankingHistory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RankingHistoryManagerCommand extends Command
{
    protected static $defaultName = 'app:ranking:history:add';

    /** RankingHistoryManagerCommand constructor. */
    public function __construct(private readonly EntityManagerInterface $em, private readonly LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Saves current ranking stats.')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Start ranking history.');

        $currentRanking = $this->em->getRepository(Ranking::class)->findCurrent();
        if (!$currentRanking instanceof Ranking) {
            $this->logger->critical('Cannot find current ranking!');
        }

        /** @var Coaster $coaster */
        foreach ($this->em->getRepository(Ranking::class)->findCoastersRanked()->getResult() as $coaster) {
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

        return 0;
    }
}
