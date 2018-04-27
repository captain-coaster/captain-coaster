<?php

namespace BddBundle\Command;

use BddBundle\Service\RankingService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RankingCommand extends ContainerAwareCommand
{
    /**
     * @var RankingService
     */
    private $rankingService;

    /**
     * RankingCommand constructor.
     * @param RankingService $rankingService
     */
    public function __construct(RankingService $rankingService)
    {
        parent::__construct();

        $this->rankingService = $rankingService;
    }

    protected function configure()
    {
        $this
            ->setName('ranking:update')
            ->addOption('dry-run', null, InputOption::VALUE_NONE)
            ->addOption('output', null, InputOption::VALUE_NONE);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = $this->getContainer()->get('debug.stopwatch');
        $stopwatch->start('ranking');

        $output->writeln('Starting update ranking command.');

        $dryRun = $input->getOption('dry-run');

        if ((new \DateTime())->format('j') !== '1' && !$dryRun) {
            $output->writeln('We are not first day of month. We do it dry-run anyway.');
            $dryRun = true;
        }

        $result = $this->rankingService->updateRanking($dryRun);

        if ($input->getOption('output')) {
            foreach ($result as $coaster) {
                $output->writeln($this->formatMessage($coaster));
            }
        }

        $output->writeln(count($result).' coasters updated.');

        $event = $stopwatch->stop('ranking');
        $output->writeln((round($event->getDuration() / 1000)).' seconds');
        $output->writeln((round($event->getMemory() / (1000 * 1000))).' Mo');
        $output->writeln('Dry-run: '.$dryRun);
    }

    /**
     * @param array $coaster
     * @return string
     */
    private function formatMessage(array $coaster): string
    {
        if (is_null($coaster[2])) {
            $message = sprintf(
                '%s-%s (<error>new</error>) %s updated.',
                $coaster[1],
                $coaster[0],
                $coaster[3]
            );
        } elseif (abs($coaster[1] - $coaster[2]) > 0.1 * $coaster[2]) {
            $message = sprintf(
                '%s-%s (<error>%s</error>) %s updated.',
                $coaster[1],
                $coaster[0],
                $coaster[2],
                $coaster[3]
            );
        } else {
            $message = sprintf(
                '%s-%s (%s) %s updated.',
                $coaster[1],
                $coaster[0],
                $coaster[2],
                $coaster[3]
            );
        }

        return $message;
    }
}
