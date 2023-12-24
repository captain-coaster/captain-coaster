<?php

namespace App\Command;

use App\Entity\Coaster;
use App\Service\DiscordService;
use App\Service\NotificationService;
use App\Service\RankingService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class RankingCommand extends Command
{
    private RankingService $rankingService;
    private NotificationService $notificationService;
    private DiscordService $discordService;

    public function __construct(RankingService $rankingService, NotificationService $notificationService, DiscordService $discordService)
    {
        parent::__construct();
        $this->rankingService = $rankingService;
        $this->notificationService = $notificationService;
        $this->discordService = $discordService;
    }

    protected function configure()
    {
        $this
            ->setName('ranking:update')
            ->addOption('dry-run', null, InputOption::VALUE_NONE)
            ->addOption('send-discord', null, InputOption::VALUE_NONE)
            ->addOption('send-email', null, InputOption::VALUE_NONE);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('ranking');
        $output->writeln('Starting update ranking command.');

        $dryRun = $input->getOption('dry-run');
        $output->writeln(($dryRun) ? 'Dry-run mode' : 'Production update');

        // dry run safety
        if ((new \DateTime())->format('j') !== '1' && !$dryRun) {
            $output->writeln('We are not first day of month. We do it dry-run anyway.');
            $dryRun = true;
        }

        // compute ranking
        $coasterList = $this->rankingService->updateRanking($dryRun);

        // output result to console
        foreach ($coasterList as $coaster) {
            $output->writeln($this->formatCoasterForConsole($coaster));
        }

        $output->writeln((string)$stopwatch->stop('ranking'));

        // notify discord
        if ($input->getOption('send-discord')) {
            $stopwatch->start('discord');
            $output->writeln('Notifying discord...');

            $this->notifyDiscord($coasterList);

            $output->writeln((string)$stopwatch->stop('discord'));
        }

        if ($input->getOption('send-email') && !$dryRun) {
            $stopwatch->start('emailing');
            $output->writeln('Sending emails to users...');

            // send notifications to everyone
            if ($this->rankingService->getHighlightedNewCoaster()) {
                $this->notificationService->sendAll('notif.ranking.messageWithNewCoaster', NotificationService::NOTIF_RANKING, $this->rankingService->getHighlightedNewCoaster());
            } else {
                $this->notificationService->sendAll('notif.ranking.message', NotificationService::NOTIF_RANKING);
            }

            $output->writeln((string)$stopwatch->stop('emailing'));
        }
    }

    private function formatCoasterForConsole(Coaster $coaster): string
    {
        $format = '[%d] %s - %s (%s)';
        if (is_null($coaster->getPreviousRank())) {
            $format = '<error>'.$format.'</error>';
        } elseif (abs($coaster->getRank() - $coaster->getPreviousRank()) > 0.25 * $coaster->getPreviousRank()) {
            $format = '<comment>'.$format.'</comment>';
        } elseif (abs($coaster->getRank() - $coaster->getPreviousRank()) > 0.1 * $coaster->getPreviousRank()) {
            $format = '<info>'.$format.'</info>';
        }

        return sprintf(
            $format,
            $coaster->getRank(),
            $coaster->getName(),
            $coaster->getPark()->getName(),
            is_null($coaster->getPreviousRank()) ? 'new' : sprintf("%+d", ($coaster->getPreviousRank() - $coaster->getRank()))
        );
    }

    private function notifyDiscord($coasterList)
    {
        $discordText = '';

        foreach ($coasterList as $coaster) {
            $text = sprintf(
                "[%d] %s - %s (%s)\n",
                $coaster->getRank(),
                (is_null($coaster->getPreviousRank())) ? '**'.$coaster->getName().'**' : $coaster->getName(),
                $coaster->getPark()->getName(),
                is_null($coaster->getPreviousRank()) ? 'new' : sprintf("%+d", ($coaster->getPreviousRank() - $coaster->getRank()))
            );

            if (strlen($discordText) + strlen($text) > 2000) {
                $this->discordService->log($discordText);
                $discordText = '';
            }

            $discordText = $discordText.$text;
        }

        $this->discordService->log($discordText);
    }
}
