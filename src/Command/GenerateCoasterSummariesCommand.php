<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Service\CoasterSummaryService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for generating AI summaries of coasters.
 */
#[AsCommand(
    name: 'app:generate-coaster-summaries',
    description: 'Generate AI summaries for coaster reviews using AWS Bedrock'
)]
class GenerateCoasterSummariesCommand extends Command
{
    /** Feedback ratio threshold for bad summaries */
    private const BAD_SUMMARY_RATIO = 0.3;

    /** Minimum votes required to consider feedback ratio */
    private const MIN_VOTES_FOR_FEEDBACK = 10;

    public function __construct(
        private CoasterRepository $coasterRepository,
        private CoasterSummaryRepository $summaryRepository,
        private CoasterSummaryService $summaryService,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    /** Configures the command options */
    protected function configure(): void
    {
        $this
            ->addOption('coaster-id', null, InputOption::VALUE_OPTIONAL, 'Generate summary for specific coaster ID')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of coasters to process', null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force regeneration of all summaries')
            ->addOption('force-bad-rated', null, InputOption::VALUE_NONE, 'Force regeneration of summaries with poor feedback (≤30% positive, ≥10 votes)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate execution without calling Bedrock API')
            ->setHelp(
                'Generates AI summaries for coasters with sufficient reviews (20+).'."\n".
                'Processes coasters in deterministic order by ID.'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:generate-coaster-summaries --limit=50'."\n".
                '  php bin/console app:generate-coaster-summaries --coaster-id=123 --force'."\n".
                '  php bin/console app:generate-coaster-summaries --force-bad-rated'."\n".
                '  php bin/console app:generate-coaster-summaries --force --dry-run'
            );
    }

    /** Executes the command to generate coaster summaries */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $coasterId = $input->getOption('coaster-id');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $force = (bool) $input->getOption('force');
        $forceBadRated = (bool) $input->getOption('force-bad-rated');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $coasters = $this->loadCoasters($coasterId, $limit);
            $badRatedCoasterIds = $forceBadRated
                ? $this->summaryRepository->findCoasterIdsWithPoorFeedback(self::BAD_SUMMARY_RATIO, self::MIN_VOTES_FOR_FEEDBACK)
                : [];
        } catch (\Exception $e) {
            $io->error("Error loading coasters: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $processed = 0;
        $generated = 0;
        $totalCoasters = \count($coasters);

        if ($forceBadRated) {
            $io->note("Processing {$totalCoasters} eligible coasters, forcing regeneration for ".\count($badRatedCoasterIds).' with poor feedback');
        } else {
            $io->note("Processing {$totalCoasters} eligible coasters".($limit ? " (limited to {$limit})" : ''));
        }

        foreach ($coasters as $coaster) {
            try {
                $isBadRated = \in_array($coaster->getId(), $badRatedCoasterIds, true);
                $shouldProcess = $force || ($forceBadRated && $isBadRated) || $this->summaryService->shouldUpdateSummary($coaster);

                if (!$shouldProcess) {
                    $io->writeln("Skipping ID {$coaster->getId()} {$coaster->getName()} (summary exists)");
                    continue;
                }

                $io->writeln("Processing ID {$coaster->getId()} {$coaster->getName()}...");

                if ($dryRun) {
                    $io->writeln('  ✓ Dry run');
                } else {
                    $this->processCoaster($coaster, $io, $generated);

                    if ($processed < $totalCoasters - 1) {
                        sleep(2);
                    }
                }

                ++$processed;
            } catch (\Exception $e) {
                $io->error("Error processing ID {$coaster->getId()} {$coaster->getName()}: {$e->getMessage()}");
                continue;
            }
        }

        $io->success("Processed {$processed} coasters, generated {$generated} summaries");

        return Command::SUCCESS;
    }

    /** Loads coasters based on command options */
    private function loadCoasters(?string $coasterId, ?int $limit): array
    {
        if ($coasterId) {
            $coaster = $this->coasterRepository->find($coasterId);
            if (!$coaster) {
                throw new \RuntimeException("Coaster with ID {$coasterId} not found");
            }

            return [$coaster];
        }

        return $this->coasterRepository->findEligibleForSummary(CoasterSummaryService::MIN_REVIEWS_REQUIRED, $limit);
    }

    /** Processes a single coaster to generate its summary */
    private function processCoaster($coaster, SymfonyStyle $io, int &$generated): void
    {
        try {
            $result = $this->summaryService->generateSummary($coaster);

            if ($result['summary']) {
                ++$generated;
                $summary = $result['summary'];
                $metadata = $result['metadata'];

                $io->writeln("  ✓ Generated: {$summary->getReviewsAnalyzed()} reviews, ".\count($summary->getDynamicPros()).' pros, '.\count($summary->getDynamicCons()).' cons');
                $io->writeln("  Performance: {$metadata['latency_ms']}ms, {$metadata['input_tokens']}+{$metadata['output_tokens']} tokens, $".number_format($metadata['cost_usd'], 4));
                $io->newLine();
            } else {
                $reason = $result['reason'] ?? 'unknown';
                $this->logger->error('Failed to generate summary', [
                    'coaster_id' => $coaster->getId(),
                    'coaster_name' => $coaster->getName(),
                    'reason' => $reason,
                ]);
                $io->writeln("  ⚠ Failed ({$reason})");
                $io->newLine();
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception while processing coaster', [
                'coaster_id' => $coaster->getId(),
                'coaster_name' => $coaster->getName(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
