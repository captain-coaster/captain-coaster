<?php

namespace BddBundle\Command;

use BddBundle\Service\TopService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TopUpdateCommand extends ContainerAwareCommand
{
    protected $topService;

    public function __construct(TopService $topService)
    {
        parent::__construct();

        $this->topService = $topService;
    }

    protected function configure()
    {
        $this
            ->setName('top:update')
            ->setDescription('Update average top rank and total tops in values for all coasters');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = $this->getContainer()->get('debug.stopwatch');
        $stopwatch->start('top');

        $output->writeln('Start updating tops.');

        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $coasters = $em->getRepository('BddBundle:Coaster')->findAll();

        foreach ($coasters as $coaster) {
            $this->topService->updateTopStats($coaster);
        }

        $output->writeln('End of update.');

        $event = $stopwatch->stop('top');
        $output->writeln((round($event->getDuration() / 1000)).' seconds');
        $output->writeln((round($event->getMemory() / (1000 * 1000))).' Mo');
    }
}
