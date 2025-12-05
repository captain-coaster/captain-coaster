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
            ->addOption('coaster-id', null, InputOption::VALUE_OPTIONAL, 'Generate summary for specific coaster ID')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of coasters to process', null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force regeneration if summary already exists')
            ->addOption('no-translate', null, InputOption::VALUE_NONE, 'Skip translation generation (English only)')
            ->addOption('translate-only', null, InputOption::VALUE_NONE, 'Only generate translations for existing English summaries (skip English generation)')
            ->addOption('languages', null, InputOption::VALUE_OPTIONAL, 'Generate translations only for specified languages (comma-separated: fr,es,de)', 'fr,es,de')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate execution without calling Bedrock API')
            ->setHelp(
                'Generates AI summaries for coasters with sufficient reviews (20+).'."\n".
                'Processes coasters in deterministic order by ID.'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:generate-coaster-summaries --limit=50'."\n".
                '  php bin/console app:generate-coaster-summaries --coaster-id=123 --force'."\n".
                '  php bin/console app:generate-coaster-summaries --no-translate'."\n".
                '  php bin/console app:generate-coaster-summaries --translate-only --languages=fr'."\n".
                '  php bin/console app:generate-coaster-summaries --coaster-id=3144 --translate-only --languages=fr --force'."\n".
                '  php bin/console app:generate-coaster-summaries --languages=fr,es'."\n".
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
        $noTranslate = (bool) $input->getOption('no-translate');
        $translateOnly = (bool) $input->getOption('translate-only');
        $languagesOption = $input->getOption('languages');
        $dryRun = (bool) $input->getOption('dry-run');

        // Validate conflicting options
        if ($noTranslate && $translateOnly) {
            $io->error('Cannot use --no-translate and --translate-only together');

            return Command::FAILURE;
        }

        // Parse target languages
        $targetLanguages = [];
        if (!$noTranslate && $languagesOption) {
            $languages = array_map('trim', explode(',', $languagesOption));
            $targetLanguages = array_values(array_intersect($languages, ['fr', 'es', 'de']));
        }

        try {
            // Load coasters
            if ($coasterId) {
                $coaster = $this->coasterRepository->find($coasterId);
                if (!$coaster) {
                    throw new \RuntimeException("Coaster with ID {$coasterId} not found");
                }
                $coasters = [$coaster];
            } elseif ($translateOnly) {
                $coasters = $this->summaryRepository->findCoastersWithSummaries('en', $limit);
            } else {
                $coasters = $this->coasterRepository->findEligibleForSummary(CoasterSummaryService::MIN_REVIEWS_REQUIRED, $limit);
            }
        } catch (\Exception $e) {
            $io->error("Error loading coasters: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $processed = 0;
        $generated = 0;
        $translationsGenerated = array_fill_keys($targetLanguages, 0);
        $totalCoasters = \count($coasters);

        $io->note("Processing {$totalCoasters} eligible coasters".($limit ? " (limited to {$limit})" : ''));

        if ($translateOnly) {
            $io->note('Translation-only mode: processing translations for existing English summaries');
        }

        if (!$noTranslate && !empty($targetLanguages)) {
            $io->note('Translation enabled for languages: '.implode(', ', $targetLanguages));
        }

        foreach ($coasters as $coaster) {
            try {
                $io->writeln("Processing ID {$coaster->getId()} {$coaster->getName()}...");

                if ($dryRun) {
                    $io->writeln('  ✓ Dry run');
                    ++$processed;
                    continue;
                }

                $englishSummary = null;

                // In translate-only mode, load existing English summary
                if ($translateOnly) {
                    $englishSummary = $this->summaryRepository->findByCoasterAndLanguage($coaster, 'en');

                    if (!$englishSummary) {
                        $io->writeln('  ⚠ Skipping (no English summary exists)');
                        continue;
                    }

                    $io->writeln('  → Using existing English summary');
                } else {
                    // Normal mode: generate or update English summary
                    $shouldProcess = $force || $this->summaryService->shouldUpdateSummary($coaster);

                    if (!$shouldProcess) {
                        $io->writeln('  → Skipping English generation (summary exists)');

                        // Load existing summary for translation
                        if (!$noTranslate && !empty($targetLanguages)) {
                            $englishSummary = $this->summaryRepository->findByCoasterAndLanguage($coaster, 'en');
                        }
                    } else {
                        // Generate English summary
                        $result = $this->summaryService->generateSummary($coaster);

                        if ($result['summary']) {
                            ++$generated;
                            $englishSummary = $result['summary'];
                            $metadata = $result['metadata'];

                            $io->writeln("  ✓ Generated: {$englishSummary->getReviewsAnalyzed()} reviews, ".\count($englishSummary->getDynamicPros()).' pros, '.\count($englishSummary->getDynamicCons()).' cons');
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
                    }
                }

                // Generate translations if we have an English summary
                if ($englishSummary && !$noTranslate && !empty($targetLanguages)) {
                    $this->processTranslations(
                        $coaster,
                        $englishSummary,
                        $targetLanguages,
                        $force,
                        $io,
                        $translationsGenerated
                    );
                }

                ++$processed;
            } catch (\Exception $e) {
                $io->error("Error processing ID {$coaster->getId()} {$coaster->getName()}: {$e->getMessage()}");
                continue;
            }
        }

        // Display summary
        $io->success("Processed {$processed} coasters, generated {$generated} English summaries");

        if (!$noTranslate && !empty($translationsGenerated)) {
            foreach ($translationsGenerated as $language => $count) {
                if ($count > 0) {
                    $io->writeln("{$language}: {$count} translations");
                }
            }
        }

        return Command::SUCCESS;
    }

    /** Processes translations for a coaster after English summary is generated */
    private function processTranslations(
        $coaster,
        $englishSummary,
        array $targetLanguages,
        bool $force,
        SymfonyStyle $io,
        array &$translationsGenerated
    ): void {
        foreach ($targetLanguages as $language) {
            try {
                $shouldTranslate = $force || $this->summaryService->shouldUpdateTranslation($coaster, $language);

                if (!$shouldTranslate) {
                    $io->writeln("  → Skipping {$language} translation (exists)");
                    continue;
                }

                $io->writeln("  → Translating to {$language}...");

                $result = $this->summaryService->translateSummary($englishSummary, $language);

                if ($result['summary']) {
                    ++$translationsGenerated[$language];
                    $metadata = $result['metadata'];

                    $io->writeln("    ✓ Translated to {$language}");
                    $io->writeln("    Performance: {$metadata['latency_ms']}ms, {$metadata['input_tokens']}+{$metadata['output_tokens']} tokens, $".number_format($metadata['cost_usd'], 4));
                } else {
                    $reason = $result['reason'] ?? 'unknown';
                    $this->logger->error('Failed to translate summary', [
                        'coaster_id' => $coaster->getId(),
                        'coaster_name' => $coaster->getName(),
                        'language' => $language,
                        'reason' => $reason,
                    ]);
                    $io->writeln("    ⚠ Translation to {$language} failed ({$reason})");
                }
            } catch (\Exception $e) {
                $this->logger->error('Exception while translating', [
                    'coaster_id' => $coaster->getId(),
                    'coaster_name' => $coaster->getName(),
                    'language' => $language,
                    'error' => $e->getMessage(),
                ]);
                $io->writeln("    ⚠ Translation to {$language} failed: {$e->getMessage()}");
            }
        }

        $io->newLine();
    }
}
