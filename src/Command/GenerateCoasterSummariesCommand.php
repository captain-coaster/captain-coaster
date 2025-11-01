<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
use App\Service\CoasterSummaryService;
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
    public function __construct(
        private CoasterRepository $coasterRepository,
        private CoasterSummaryService $summaryService
    ) {
        parent::__construct();
    }

    /** Configures the command options */
    protected function configure(): void
    {
        $this
            ->addOption('coaster-id', null, InputOption::VALUE_OPTIONAL, 'Generate summary for specific coaster ID')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of coasters to process', null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force regeneration even if summary exists')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate execution without calling Bedrock API')
            ->addOption('min-ratio', null, InputOption::VALUE_OPTIONAL, 'Regenerate summaries with feedback ratio below this threshold (0.0-1.0)', null)
            ->addOption('min-votes', null, InputOption::VALUE_OPTIONAL, 'Minimum votes required to consider feedback ratio (default: 10)', '10')
            ->setHelp(
                'This command generates AI summaries for coasters, processing ranked coasters in order (1, 2, 3...).'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:generate-coaster-summaries --limit=50'."\n".
                '  php bin/console app:generate-coaster-summaries --coaster-id=123 --force'."\n".
                '  php bin/console app:generate-coaster-summaries --min-ratio=0.3 --min-votes=5'."\n".
                '  php bin/console app:generate-coaster-summaries --min-ratio=0.2 --dry-run'
            );
    }

    /** Executes the command to generate coaster summaries */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $coasterId = $input->getOption('coaster-id');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');
        $minRatio = $input->getOption('min-ratio') ? (float) $input->getOption('min-ratio') : null;
        $minVotes = (int) $input->getOption('min-votes');

        // Validate min-ratio parameter
        if ($minRatio !== null && ($minRatio < 0.0 || $minRatio > 1.0)) {
            $io->error('The --min-ratio option must be between 0.0 and 1.0');
            return Command::FAILURE;
        }

        try {
            if ($coasterId) {
                $coaster = $this->coasterRepository->find($coasterId);
                if (!$coaster) {
                    $io->error("Coaster with ID {$coasterId} not found");

                    return Command::FAILURE;
                }
                $coasters = [$coaster];
            } elseif ($minRatio !== null) {
                // Get summaries with poor feedback ratios
                $summaries = $this->summaryService->getSummariesWithPoorFeedback($minRatio, $minVotes);
                $coasters = array_map(fn($summary) => $summary->getCoaster(), $summaries);
                
                if ($limit) {
                    $coasters = array_slice($coasters, 0, $limit);
                }
                
                $io->note("Found " . count($summaries) . " summaries with feedback ratio ≤ " . ($minRatio * 100) . "% and ≥ {$minVotes} votes");
            } else {
                // Get ranked coasters ordered by rank (1, 2, 3...)
                $queryBuilder = $this->coasterRepository->createQueryBuilder('c')
                    ->where('c.enabled = :enabled')
                    ->andWhere('c.rank IS NOT NULL')
                    ->andWhere('c.rank >= 1')
                    ->orderBy('c.rank', 'ASC')
                    ->setParameter('enabled', true);

                if ($limit) {
                    $queryBuilder->setMaxResults($limit);
                }

                $coasters = $queryBuilder->getQuery()->getResult();
            }
        } catch (\Exception $e) {
            $io->error("Error loading coasters: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $processed = 0;
        $generated = 0;
        $totalCoasters = \count($coasters);

        if ($minRatio !== null) {
            $io->note("Processing {$totalCoasters} coasters with poor feedback ratios".($limit ? " (limited to {$limit})" : ''));
        } else {
            $io->note("Processing {$totalCoasters} ranked coasters".($limit ? " (limited to {$limit})" : ''));
        }

        foreach ($coasters as $coaster) {
            try {
                // For feedback filtering mode, always force regeneration since we're targeting poor summaries
                $shouldProcess = $minRatio !== null || $force || $this->summaryService->shouldUpdateSummary($coaster);
                
                if (!$shouldProcess) {
                    $io->writeln("Skipping #{$coaster->getRank()} {$coaster->getName()} (summary exists)");
                    continue;
                }

                $rankDisplay = $coaster->getRank() ? "#{$coaster->getRank()}" : "#?";
                $io->write("Processing {$rankDisplay} {$coaster->getName()}... ");

                if ($dryRun) {
                    $io->writeln('  ✓ Dry run - would generate summary');
                } else {
                    $this->processCoaster($coaster, $io, $generated);

                    // AWS Bedrock rate limiting for GPT OSS 120b
                    // Conservative approach: 1 request per 2 seconds to stay well under quota
                    if ($processed < $totalCoasters - 1) { // Don't sleep after last item
                        $io->writeln('  Waiting 2s for AWS quota management...');
                        sleep(2);
                    }
                }

                ++$processed;
            } catch (\Exception $e) {
                $rankDisplay = $coaster->getRank() ? "#{$coaster->getRank()}" : "#?";
                $io->error("Error processing coaster {$rankDisplay} {$coaster->getName()}: {$e->getMessage()}");
                continue;
            }
        }

        $io->success("Processed {$processed} coasters, generated {$generated} summaries");

        return Command::SUCCESS;
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

                $prosCount = \count($summary->getDynamicPros());
                $consCount = \count($summary->getDynamicCons());
                $reviewsAnalyzed = $summary->getReviewsAnalyzed();

                $io->writeln("  ✓ Generated summary ({$reviewsAnalyzed} reviews, {$prosCount} pros, {$consCount} cons)");
                $io->writeln("  Latency: {$metadata['latency_ms']}ms, Input: {$metadata['input_tokens']}, Output: {$metadata['output_tokens']}, Cost: $".number_format($metadata['cost_usd'], 4));
            } else {
                $io->writeln('  ⚠ Failed to generate summary');
            }
        } catch (\Exception $e) {
            $io->error("  Error processing coaster: {$e->getMessage()}");
        }
    }
}
