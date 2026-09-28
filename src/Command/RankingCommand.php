<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
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

#[AsCommand(
    name: 'ranking:update',
    description: 'Computes the monthly ranking and stages it for ranking:publish.',
)]
class RankingCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly RankingService $rankingService,
        private readonly RankingRepository $rankingRepository,
        private readonly CoasterRepository $coasterRepository,
        private readonly RankingDiscord $discord,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.ranking.hold_on_anomalies')]
        private readonly bool $holdOnAnomalies,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compute and report only, write nothing')
            ->addOption('regenerate', null, InputOption::VALUE_NONE, 'Recompute the published ranking and republish it now')
            ->addOption('send-discord', null, InputOption::VALUE_NONE, 'Post a dry run\'s report to Discord (a real run always does)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock('ranking')) {
            $output->writeln('<error>Another ranking command is running.</error>');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $regenerate = (bool) $input->getOption('regenerate');
        $last = $this->rankingRepository->findLastPublished();

        if ($regenerate && null === $last) {
            $output->writeln('<error>No published ranking to regenerate.</error>');

            return Command::FAILURE;
        }
        // Otherwise the month after the last published: a ranking computed early waits for its publication time
        $publishedAt = $last?->getPublishedAt();
        $month = $regenerate ? $last->getMonth() : RankingService::targetMonth($last?->getMonth(), new \DateTimeImmutable());

        $output->writeln(\sprintf('%s of %s', $dryRun ? 'Dry run' : 'Ranking', $month->format('F Y')));

        try {
            $start = hrtime(true);
            $result = $this->rankingService->compute();
            $run = [
                'durationMs' => intdiv(hrtime(true) - $start, 1_000_000),
                'peakMemoryMb' => intdiv(memory_get_peak_usage(true), 1024 * 1024),
            ];
            $report = $this->rankingService->report($result, $month);
            $names = $this->coasterRepository->findDisplayNames(array_merge(array_keys($result->ranks()), array_keys($report->left)));
            $summary = RankingDiscord::summary($report, $names);
            $summary[] = \sprintf('Computed in %.1fs, %d MB', $run['durationMs'] / 1000, $run['peakMemoryMb']);

            $output->writeln(['', ...$summary]);

            if ($dryRun) {
                if ($input->getOption('send-discord')) {
                    $this->discord->send([\sprintf('**Ranking dry run, %s**', $month->format('F Y')), ...$summary]);
                }

                return Command::SUCCESS;
            }

            // A pending ranking of $month is replaced
            $ranking = $this->rankingService->stage($result, $report, $month, $run, $regenerate);
            if ($regenerate) {
                $this->rankingService->publish($ranking, $publishedAt);
                $status = 'Republished';
            } else {
                $status = $report->anomalies && $this->holdOnAnomalies
                    ? '⚠️ Held: ranking:publish --force to publish it anyway'
                    : \sprintf('Publication: %s UTC', RankingService::publicationTime($month)->format('Y-m-d H:i'));
            }
            $output->writeln(['', \sprintf('Staged ranking #%d. %s', $ranking->getId(), $status)]);
            $this->discord->send([\sprintf('**Ranking of %s computed**', $month->format('F Y')), ...$summary, $status]);
        } catch (\Throwable $e) {
            $this->logger->critical('Ranking computation failed: '.$e->getMessage(), ['exception' => $e]);
            if (!$dryRun || $input->getOption('send-discord')) {
                $this->discord->alert(\sprintf('🚨 **Ranking of %s failed**: %s', $month->format('F Y'), $e->getMessage()));
            }

            throw $e;
        }

        return Command::SUCCESS;
    }
}
