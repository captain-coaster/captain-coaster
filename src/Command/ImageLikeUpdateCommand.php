<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImageLikeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:image:update-likes',
    description: 'Recalculate like counters for all images',
)]
class ImageLikeUpdateCommand extends Command
{
    public function __construct(
        private readonly ImageLikeService $imageLikeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Updating image like counters');

        try {
            $io->info('Recalculating like counters for all images...');
            $this->imageLikeService->updateAllLikeCounts();
            $io->success('All image like counters have been updated successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred while updating image like counters: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
