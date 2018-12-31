<?php

namespace BddBundle\Command;

use BddBundle\Entity\Coaster;
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
     * @throws \Exception
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

        $output->writeln((string)$stopwatch->stop('ranking'));
        $output->writeln('Dry-run: '.$dryRun);
    }

    /**
     * @param Coaster $coaster
     * @return string
     */
    private function formatMessage(Coaster $coaster): string
    {
        $format = '[%s] %s - %s (%s) %s updated.';

        if (is_null($coaster->getPreviousRank())) {
            $format = '[%s] <error>%s</error> - %s (%s) %s updated.';
        } elseif (abs($coaster->getRank() - $coaster->getPreviousRank()) > 0.25 * $coaster->getPreviousRank()) {
            $format = '[%s] <comment>%s</comment> - %s (%s) %s updated.';
        } elseif (abs($coaster->getRank() - $coaster->getPreviousRank()) > 0.1 * $coaster->getPreviousRank()) {
            $format = '[%s] <info>%s</info> - %s (%s) %s updated.';
        }

        return sprintf(
            $format,
            $coaster->getRank(),
            $coaster->getName(),
            $coaster->getPark()->getName(),
            $coaster->getPreviousRank() ?? 'new',
            number_format($coaster->getScore(), 2)
        );
    }
}
