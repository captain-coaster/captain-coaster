<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReviewScoreService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:review:update-scores',
    description: 'Update scores for all reviews',
)]
class ReviewScoreUpdateCommand extends Command
{
    public function __construct(
        private readonly ReviewScoreService $scoreService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Updating review scores');

        try {
            $io->info('Starting score update for all reviews...');
            $this->scoreService->updateAllScores();
            $io->success('All review scores have been updated successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while updating review scores: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
