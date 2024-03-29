<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RatingService;
use App\Service\TopService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'coaster:update',
    description: 'Update average rating and total ratings calculated values for all coasters.',
    hidden: false,
)]
class CoasterUpdateCommand extends Command
{
    public function __construct(
        private readonly RatingService $ratingService,
        private readonly TopService $topService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        return Command::SUCCESS;
    }
}
