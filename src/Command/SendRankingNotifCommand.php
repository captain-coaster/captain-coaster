<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Ranking;
use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'app:send-ranking-notif',
    description: 'Add a short description for your command',
)]
class SendRankingNotifCommand extends Command
{
    public function __construct(
        private readonly RankingRepository $rankingRepository,
        private readonly CoasterRepository $coasterRepository,
        private readonly NotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();

        $stopwatch->start('emailing');

        $dryRun = $input->getOption('dry-run');

        // dry run safety
        if ('1' !== (new \DateTime())->format('j') && !$dryRun) {
            $io->info('We are not first day of month. We do it dry-run anyway.');
            $dryRun = true;
        }

        $currentRanking = $this->rankingRepository->findCurrent();
        if (!$currentRanking instanceof Ranking) {
            $io->error('Cannot find current ranking!');
        }

        if ($currentRanking->getComputedAt() < (new \DateTime())->modify('-1 hour')) {
            $io->error('Ranking not computed yet!');

            return Command::FAILURE;
        }

        $io->info('Sending notifications to users...');

        if ($dryRun) {
            $io->note('Dry run mode enabled. No emails will be sent.');

            return Command::SUCCESS;
        }

        $highlightedCoaster = $this->coasterRepository->getNewlyRankedHighlightedCoaster();

        // send notifications to everyone
        if ($highlightedCoaster) {
            $io->info('Highlighted coaster is: '.$highlightedCoaster);
            $this->notificationService->sendAll('notif.ranking.messageWithNewCoaster', NotificationService::NOTIF_RANKING, $highlightedCoaster->getName());
        } else {
            $io->info('No highlighted coaster');
            $this->notificationService->sendAll('notif.ranking.message', NotificationService::NOTIF_RANKING);
        }

        $output->writeln((string) $stopwatch->stop('emailing'));

        return Command::SUCCESS;
    }
}
