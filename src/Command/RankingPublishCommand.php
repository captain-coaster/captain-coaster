<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RankingRepository;
use App\Service\Ranking\RankingDiscord;
use App\Service\RankingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Publishes the staged ranking once its publication time has come. Safe to run often (e.g. hourly): it does nothing
 * before that time, nor once published.
 */
#[AsCommand(
    name: 'ranking:publish',
    description: 'Publishes the ranking staged by ranking:update, at its publication time.',
)]
class RankingPublishCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly RankingService $rankingService,
        private readonly RankingRepository $rankingRepository,
        private readonly RankingDiscord $discord,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.ranking.hold_on_anomalies')]
        private readonly bool $holdOnAnomalies,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Publish now, even before the publication time or with anomalies (when they hold it)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock('ranking')) {
            $output->writeln('<error>Another ranking command is running.</error>');

            return Command::FAILURE;
        }

        $ranking = $this->rankingRepository->findPending();
        if (null === $ranking) {
            $output->writeln('No ranking to publish.');

            return Command::SUCCESS;
        }

        $force = (bool) $input->getOption('force');
        $month = $ranking->getMonth()->format('F Y');
        $publicationTime = RankingService::publicationTime($ranking->getMonth());

        if (!$force && new \DateTimeImmutable() < $publicationTime) {
            $output->writeln(\sprintf('Ranking of %s: publication at %s UTC.', $month, $publicationTime->format('Y-m-d H:i')));

            return Command::SUCCESS;
        }

        if (!$force && $ranking->getAnomalies() && $this->holdOnAnomalies) {
            $message = \sprintf('⚠️ **Ranking of %s held**: %s. Check it, then ranking:publish --force.', $month, implode('; ', $ranking->getAnomalies()));
            $output->writeln($message);
            $this->discord->alert($message);

            return Command::FAILURE;
        }

        try {
            $this->rankingService->publish($ranking);
        } catch (\Throwable $e) {
            $this->logger->critical('Ranking publication failed: '.$e->getMessage(), ['exception' => $e]);
            $this->discord->alert(\sprintf('🚨 **Ranking of %s: publication failed**: %s', $month, $e->getMessage()));

            throw $e;
        }

        $output->writeln(\sprintf('Ranking of %s published.', $month));
        $this->discord->alert(\sprintf('✅ **Ranking of %s published**', $month).($ranking->getAnomalies() ? ' despite: '.implode('; ', $ranking->getAnomalies()) : ''));

        return Command::SUCCESS;
    }
}
