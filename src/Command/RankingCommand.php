<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Service\Ranking\RankingDiscord;
use App\Service\Ranking\RankingResult;
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
            ->addOption('force', null, InputOption::VALUE_NONE, 'Run on another day than the 1st, or replace this month\'s ranking')
            ->addOption('send-discord', null, InputOption::VALUE_NONE, 'Post a dry run\'s full ranking to Discord (a real run always posts its report)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock('ranking')) {
            $output->writeln('<error>Another ranking command is running.</error>');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $now = new \DateTimeImmutable();
        // A dry run previews the next ranking to be published
        $month = RankingService::monthOf($dryRun ? RankingService::nextPublication($now) : $now);

        if (!$dryRun && !$force) {
            if ('1' !== $now->setTimezone(new \DateTimeZone('UTC'))->format('j')) {
                $output->writeln('<error>Not the 1st of the month (UTC): run with --dry-run, or --force.</error>');

                return Command::FAILURE;
            }
            if (null !== $existing = $this->rankingRepository->findOneBy(['month' => $month])) {
                $output->writeln(\sprintf('<error>%s already has a ranking (#%d): run with --force to replace it.</error>', $month->format('F Y'), $existing->getId()));

                return Command::FAILURE;
            }
        }

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
            $previousRanks = ($previous = $this->rankingRepository->findPublishedBefore($month)) ? $this->rankingRepository->findRanks($previous) : [];

            $list = $this->formatRanking($result, $previousRanks, $names);
            $summary = RankingDiscord::summary($report, $names);
            $summary[] = \sprintf('Computed in %.1fs, %d MB', $run['durationMs'] / 1000, $run['peakMemoryMb']);

            $output->writeln($list);
            $output->writeln(['', ...$summary]);

            if ($dryRun) {
                if ($input->getOption('send-discord')) {
                    $this->discord->send([\sprintf('**Ranking dry run, %s**', $month->format('F Y')), ...$summary, '', ...$list]);
                }

                return Command::SUCCESS;
            }

            $ranking = $this->rankingService->stage($result, $report, $month, $run, $force);
            $status = $report->anomalies && $this->holdOnAnomalies
                ? '⚠️ Held: ranking:publish --force to publish it anyway'
                : \sprintf('Publication: %s UTC', RankingService::publicationTime($month)->format('Y-m-d H:i'));
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

    /**
     * @param array<int, int>    $previousRanks
     * @param array<int, string> $names
     *
     * @return list<string>
     */
    private function formatRanking(RankingResult $result, array $previousRanks, array $names): array
    {
        $lines = [];
        foreach ($result->coasters as $index => $coaster) {
            $rank = $index + 1;
            $previous = $previousRanks[$coaster->coaster] ?? null;
            $lines[] = \sprintf('[%d] %s (score: %.2f) (%s)', $rank, $names[$coaster->coaster] ?? '#'.$coaster->coaster, $coaster->score, null === $previous ? 'new' : \sprintf('%+d', $previous - $rank));
        }

        return $lines;
    }
}
