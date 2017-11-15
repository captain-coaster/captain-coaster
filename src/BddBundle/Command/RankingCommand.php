<?php

namespace BddBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RankingCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ranking:update')
            ->addOption('dry-run', null, InputOption::VALUE_NONE)
            ->addOption('debug', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting update ranking command.');

        $rankingService = $this->getContainer()->get('BddBundle\Service\RankingService');

        $result = $rankingService->updateRanking($input->getOption('dry-run'));

        if ($input->getOption('debug')) {
            foreach ($result as $coaster) {
                $output->writeln($this->formatMessage($coaster));
            }
        }

        $output->writeln(count($result).' coasters updated.');
    }

    private function formatMessage(array $coaster):string
    {
        if (is_null($coaster[2])) {
            $message = sprintf(
                '%s-%s (<error>new</error>) %s updated.',
                $coaster[1],
                $coaster[0],
                $coaster[3]
            );
        } elseif (abs($coaster[1] - $coaster[2]) > 0.1*$coaster[2]) {
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
