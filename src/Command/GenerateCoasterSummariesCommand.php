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
            ->addOption('coaster-id', null, InputOption::VALUE_OPTIONAL, 'Generate summary for one specific coaster - always regenerates it, no due-ness check')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of coasters to process', null)
            ->addOption('min-downvotes', null, InputOption::VALUE_OPTIONAL, 'Bulk-regenerate coasters that already have a summary in the target language with at least this many downvotes (0 = every existing summary, regardless of votes). Requires a single --languages value.', null)
            ->addOption('languages', null, InputOption::VALUE_OPTIONAL, 'Target languages for generation (comma-separated: en,fr,es,de)', 'en')
            ->addOption('no-vocab-guide', null, InputOption::VALUE_NONE, 'Skip the vocabulary guide prompt section - for real (persisted) A/B testing of whether it still helps')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate execution without calling Bedrock API')
            ->setHelp(
                'Generates AI summaries for coasters with sufficient reviews (20+).'."\n".
                'Uses direct generation for all languages without translation.'."\n".
                'Processes coasters in deterministic order by ID.'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:generate-coaster-summaries --limit=50'."\n".
                '  php bin/console app:generate-coaster-summaries --coaster-id=123'."\n".
                '  php bin/console app:generate-coaster-summaries --languages=en,fr,es'."\n".
                '  php bin/console app:generate-coaster-summaries --languages=fr --min-downvotes=5'."\n".
                '  php bin/console app:generate-coaster-summaries --languages=fr --min-downvotes=0'."\n".
                '  php bin/console app:generate-coaster-summaries --coaster-id=123 --languages=fr --no-vocab-guide'."\n".
                '  php bin/console app:generate-coaster-summaries --dry-run'
            );
    }

    /** Executes the command to generate coaster summaries */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $coasterId = $input->getOption('coaster-id');
        $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $minDownvotesOption = $input->getOption('min-downvotes');
        $minDownvotes = null !== $minDownvotesOption ? (int) $minDownvotesOption : null;
        $languagesOption = $input->getOption('languages');
        $includeVocabularyGuide = !$input->getOption('no-vocab-guide');
        $dryRun = (bool) $input->getOption('dry-run');

        // Parse target languages
        $targetLanguages = [];
        if ($languagesOption) {
            $languages = array_map('trim', explode(',', $languagesOption));
            $targetLanguages = array_values(array_intersect($languages, ['en', 'fr', 'es', 'de']));
        }

        // Default to English if no valid languages specified
        if (empty($targetLanguages)) {
            $targetLanguages = ['en'];
        }

        if (null !== $minDownvotes && $coasterId) {
            $io->error('--min-downvotes and --coaster-id cannot be combined - --coaster-id already always regenerates that one coaster regardless of votes, so --min-downvotes would have no effect.');

            return Command::FAILURE;
        }

        if (null !== $minDownvotes && 1 !== \count($targetLanguages)) {
            $io->error('--min-downvotes requires exactly one --languages value (it targets a summary in that specific language - a downvoted EN summary should not also force-regenerate a good FR one).');

            return Command::FAILURE;
        }

        try {
            // Load coasters
            if ($coasterId) {
                $coaster = $this->coasterRepository->find($coasterId);
                if (!$coaster) {
                    $io->error("Coaster with ID {$coasterId} not found");
                    $io->note('Suggestions:');
                    $io->listing([
                        'Check if the coaster ID is correct',
                        'Use the admin interface to verify coaster exists',
                        'Run without --coaster-id to process all eligible coasters',
                    ]);

                    return Command::FAILURE;
                }
                $coasters = [$coaster];
            } elseif (null !== $minDownvotes) {
                $coasters = $this->summaryRepository->findCoastersWithBadReviews($targetLanguages[0], $minDownvotes, $limit);
            } else {
                // Scope the eligibility count to the target language when there's only one,
                // so a --limit doesn't get wasted on coasters that are eligible in some
                // other language but not the one actually being generated.
                $eligibilityLanguage = 1 === \count($targetLanguages) ? $targetLanguages[0] : null;
                $coasters = $this->coasterRepository->findEligibleForSummary(CoasterSummaryService::MIN_REVIEWS_REQUIRED, $limit, $eligibilityLanguage);
            }
        } catch (\Exception $e) {
            $io->error("Error loading coasters: {$e->getMessage()}");
            $io->note('Troubleshooting steps:');
            $io->listing([
                'Check database connection and credentials',
                'Verify the database schema is up to date (run migrations)',
                'Check if the database server is running',
                'Review application logs for more details',
            ]);

            return Command::FAILURE;
        }

        $processed = 0;
        $summariesGenerated = array_fill_keys($targetLanguages, 0);
        $totalCoasters = \count($coasters);

        $io->note("Processing {$totalCoasters} eligible coasters".($limit ? " (limited to {$limit})" : ''));
        $io->note('Target languages: '.implode(', ', $targetLanguages));

        if (null !== $minDownvotes) {
            $io->note("Force regeneration mode: {$targetLanguages[0]} summaries with {$minDownvotes}+ downvotes".(0 === $minDownvotes ? ' (i.e. every existing summary)' : ''));
        }

        if (!$includeVocabularyGuide) {
            $io->note('Vocabulary guide disabled for this run.');
        }

        foreach ($coasters as $coaster) {
            try {
                $io->writeln("Processing ID {$coaster->getId()} {$coaster->getName()}...");

                if ($dryRun) {
                    $io->writeln('  ✓ Dry run');
                    ++$processed;
                    continue;
                }

                // Process each target language
                foreach ($targetLanguages as $language) {
                    // Explicitly targeting one coaster or a downvote threshold is already
                    // a deliberate choice - skip the due-ness check in both cases.
                    $shouldProcess = null !== $coasterId
                        || null !== $minDownvotes
                        || $this->summaryService->shouldUpdateSummary($coaster, $language);

                    if (!$shouldProcess) {
                        $io->writeln("  → Skipping {$language} (summary exists)");
                        continue;
                    }

                    $io->writeln("  → Generating {$language} summary...");

                    $result = $this->summaryService->generateSummary($coaster, null, $language, $includeVocabularyGuide);

                    if ($result['summary']) {
                        ++$summariesGenerated[$language];
                        $summary = $result['summary'];
                        $metadata = $result['metadata'];

                        $io->writeln("    ✓ Generated: {$summary->getReviewsAnalyzed()} reviews, ".\count($summary->getDynamicPros()).' pros, '.\count($summary->getDynamicCons()).' cons');
                        $io->writeln("    Performance: {$metadata['latency_ms']}ms, {$metadata['input_tokens']}+{$metadata['output_tokens']} tokens, $".number_format($metadata['cost_usd'], 4));
                    } else {
                        $reason = $result['reason'] ?? 'unknown';
                        $this->logger->error('Failed to generate summary', [
                            'coaster_id' => $coaster->getId(),
                            'coaster_name' => $coaster->getName(),
                            'language' => $language,
                            'reason' => $reason,
                            'review_count' => $result['review_count'] ?? null,
                            'metadata' => $result['metadata'] ?? null,
                        ]);

                        $failureMessage = "    ⚠ {$language} failed ({$reason})";
                        if ('insufficient_reviews' === $reason) {
                            $reviewCount = $result['review_count'] ?? 0;
                            $failureMessage .= " - only {$reviewCount} reviews (need ".CoasterSummaryService::MIN_REVIEWS_REQUIRED.'+)';
                        } elseif ('ai_error' === $reason) {
                            $failureMessage .= ' - check AWS Bedrock service status and API limits';
                        }
                        $io->writeln($failureMessage);
                    }
                }

                $io->newLine();
                ++$processed;
            } catch (\Exception $e) {
                $errorMessage = "Error processing ID {$coaster->getId()} {$coaster->getName()}: {$e->getMessage()}";
                $io->error($errorMessage);

                $this->logger->error('Command execution error', [
                    'coaster_id' => $coaster->getId(),
                    'coaster_name' => $coaster->getName(),
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'languages' => $targetLanguages,
                ]);

                if (str_contains($e->getMessage(), 'memory')) {
                    $io->note('Memory issue detected. Try reducing --limit or processing fewer coasters at once.');
                } elseif (str_contains($e->getMessage(), 'timeout')) {
                    $io->note('Timeout detected. The AI service may be overloaded. Try again later.');
                } elseif (str_contains($e->getMessage(), 'connection')) {
                    $io->note('Connection issue detected. Check network connectivity and AWS credentials.');
                }

                continue;
            }
        }

        // Display summary
        $io->success("Processed {$processed} coasters");

        foreach ($summariesGenerated as $language => $count) {
            if ($count > 0) {
                $io->writeln("{$language}: {$count} summaries generated");
            }
        }

        return Command::SUCCESS;
    }
}
