<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RiddenCoasterRepository;
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
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly TopService $topService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $output->writeln('Start updating ratings...');
        $this->riddenCoasterRepository->updateTotalRatings();
        $ratingNumber = $this->riddenCoasterRepository->updateAverageRatings();
        $output->writeln(\sprintf('%d ratings updated.', \is_int($ratingNumber) ? $ratingNumber : 0));

        $output->writeln('Start updating tops...');
        $topNumber = $this->topService->updateTopStats();
        $output->writeln("$topNumber tops updated.");

        $output->writeln((string) $stopwatch->stop('command'));

        return Command::SUCCESS;
    }
}
