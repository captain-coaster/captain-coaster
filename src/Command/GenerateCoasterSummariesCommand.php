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
            ->setHelp('This command generates AI summaries for coasters, processing ranked coasters in order (1, 2, 3...).');
    }

    /** Executes the command to generate coaster summaries */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $coasterId = $input->getOption('coaster-id');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            if ($coasterId) {
                $coaster = $this->coasterRepository->find($coasterId);
                if (!$coaster) {
                    $io->error("Coaster with ID {$coasterId} not found");

                    return Command::FAILURE;
                }
                $coasters = [$coaster];
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

        $io->note("Processing {$totalCoasters} ranked coasters".($limit ? " (limited to {$limit})" : ''));

        foreach ($coasters as $coaster) {
            try {
                if (!$force && !$this->summaryService->shouldUpdateSummary($coaster)) {
                    $io->writeln("Skipping #{$coaster->getRank()} {$coaster->getName()} (summary exists)");
                    continue;
                }

                $io->write("Processing #{$coaster->getRank()} {$coaster->getName()}... ");

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
                $io->error("Error processing coaster #{$coaster->getRank()} {$coaster->getName()}: {$e->getMessage()}");
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
