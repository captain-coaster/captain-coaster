<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RiddenCoasterRepository;
use App\Service\ImageLikeService;
use App\Service\ReviewScoreService;
use App\Service\TopService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stats:update',
    description: 'Update all calculated statistics (coaster ratings, review scores, image likes, top stats)',
)]
class StatsUpdateCommand extends Command
{
    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly TopService $topService,
        private readonly ReviewScoreService $reviewScoreService,
        private readonly ImageLikeService $imageLikeService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('coasters', null, InputOption::VALUE_NONE, 'Update coaster average ratings and total ratings')
            ->addOption('tops', null, InputOption::VALUE_NONE, 'Update top statistics')
            ->addOption('reviews', null, InputOption::VALUE_NONE, 'Update review scores')
            ->addOption('images', null, InputOption::VALUE_NONE, 'Update image like counters');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $runCoasters = $input->getOption('coasters');
        $runTops = $input->getOption('tops');
        $runReviews = $input->getOption('reviews');
        $runImages = $input->getOption('images');

        // If no specific option, run all
        $runAll = !$runCoasters && !$runTops && !$runReviews && !$runImages;

        $io->title('Updating statistics');

        if ($runAll || $runCoasters) {
            $io->section('Coaster ratings');
            $this->riddenCoasterRepository->updateTotalRatings();
            $count = $this->riddenCoasterRepository->updateAverageRatings();
            $io->success(\sprintf('%d coaster ratings updated.', \is_int($count) ? $count : 0));
        }

        if ($runAll || $runTops) {
            $io->section('Top statistics');
            $count = $this->topService->updateTopStats();
            $io->success(\sprintf('%d tops updated.', $count));
        }

        if ($runAll || $runReviews) {
            $io->section('Review scores');
            $this->reviewScoreService->updateAllScores();
            $io->success('Review scores updated.');
        }

        if ($runAll || $runImages) {
            $io->section('Image like counters');
            $this->imageLikeService->updateAllLikeCounts();
            $io->success('Image like counters updated.');
        }

        $io->success('Statistics update complete.');

        return Command::SUCCESS;
    }
}
