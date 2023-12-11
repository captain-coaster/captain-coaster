<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RatingService;
use App\Service\TopService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class CoasterUpdateCommand extends Command
{
    protected static $defaultName = 'coaster:update';
    /** @var RatingService */
    protected $ratingService;

    /** @var TopService */
    protected $topService;

    /** RatingUpdateCommand constructor. */
    public function __construct(RatingService $ratingService, TopService $topService)
    {
        parent::__construct();
        $this->ratingService = $ratingService;
        $this->topService = $topService;
    }

    protected function configure()
    {
        $this->setDescription('Update average rating and total ratings calculated values for all coasters');
    }

    /** @return int|void|null */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $output->writeln('Start updating ratings...');
        $ratingNumber = $this->ratingService->updateRatings();
        $output->writeln("$ratingNumber ratings updated.");

        $output->writeln('Start updating tops...');
        $topNumber = $this->topService->updateTopStats();
        $output->writeln("$topNumber tops updated.");

        $output->writeln((string) $stopwatch->stop('command'));
    }
}
