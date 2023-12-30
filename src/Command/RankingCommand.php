<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Service\NotificationService;
use App\Service\RankingService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Stopwatch\Stopwatch;

class RankingCommand extends Command
{
    protected static $defaultName = 'ranking:update';

    public function __construct(private readonly RankingService $rankingService, private readonly NotificationService $notificationService, private readonly ChatterInterface $chatter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE)
            ->addOption('send-discord', null, InputOption::VALUE_NONE)
            ->addOption('send-email', null, InputOption::VALUE_NONE);
    }

    /** @throws \Exception */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('ranking');
        $output->writeln('Starting update ranking command.');

        $dryRun = $input->getOption('dry-run');

        // dry run safety
        if ('1' !== (new \DateTime())->format('j') && !$dryRun) {
            $output->writeln('We are not first day of month. We do it dry-run anyway.');
            $dryRun = true;
        }

        $output->writeln(($dryRun) ? 'Dry-run mode' : 'Production update');

        // compute ranking
        $coasterList = $this->rankingService->updateRanking($dryRun);

        // output result to console
        foreach ($coasterList as $coaster) {
            $output->writeln($this->formatCoasterForConsole($coaster));
        }

        $output->writeln((string) $stopwatch->stop('ranking'));

        // send recap to discord only if dry run mode
        if ($input->getOption('send-discord') && $dryRun) {
            $stopwatch->start('discord');
            $output->writeln('Notifying discord...');

            $this->notifyDiscord($coasterList);

            $output->writeln((string) $stopwatch->stop('discord'));
        }

        // send email notif only if NOT dry run mode
        if ($input->getOption('send-email') && !$dryRun) {
            $stopwatch->start('emailing');
            $output->writeln('Sending emails to users...');

            // send notifications to everyone
            if ($this->rankingService->getHighlightedNewCoaster()) {
                $this->notificationService->sendAll('notif.ranking.messageWithNewCoaster', NotificationService::NOTIF_RANKING, $this->rankingService->getHighlightedNewCoaster());
            } else {
                $this->notificationService->sendAll('notif.ranking.message', NotificationService::NOTIF_RANKING);
            }

            $output->writeln((string) $stopwatch->stop('emailing'));
        }

        return 0;
    }

    private function formatCoasterForConsole(Coaster $coaster): string
    {
        $format = '[%d] %s - %s (%s)';
        if (null === $coaster->getPreviousRank()) {
            $format = '<error>'.$format.'</error>';
        } elseif (abs($coaster->getRank() - $coaster->getPreviousRank()) > 0.25 * $coaster->getPreviousRank()) {
            $format = '<comment>'.$format.'</comment>';
        } elseif (abs($coaster->getRank() - $coaster->getPreviousRank()) > 0.1 * $coaster->getPreviousRank()) {
            $format = '<info>'.$format.'</info>';
        }

        return sprintf($format, $coaster->getRank(), $coaster->getName(), $coaster->getPark()->getName(), null === $coaster->getPreviousRank() ? 'new' : sprintf('%+d', $coaster->getPreviousRank() - $coaster->getRank()));
    }

    private function notifyDiscord($coasterList): void
    {
        $discordText = '';

        foreach ($coasterList as $coaster) {
            $text = sprintf("[%d] %s - %s (%s)\n", $coaster->getRank(), (null === $coaster->getPreviousRank()) ? '**'.$coaster->getName().'**' : $coaster->getName(), $coaster->getPark()->getName(), null === $coaster->getPreviousRank() ? 'new' : sprintf('%+d', $coaster->getPreviousRank() - $coaster->getRank()));

            if (\strlen($discordText) + \strlen($text) > 2000) {
                $this->chatter->send((new ChatMessage($discordText))->transport('discord_log'));
                $discordText = '';

                // avoid discord rate limit
                sleep(2);
            }

            $discordText .= $text;
        }

        $this->chatter->send((new ChatMessage($discordText))->transport('discord_log'));
    }
}
