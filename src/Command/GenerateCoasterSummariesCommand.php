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

#[AsCommand(
    name: 'app:generate-coaster-summaries',
    description: 'Generate summaries for coaster reviews'
)]
class GenerateCoasterSummariesCommand extends Command
{
    public function __construct(
        private CoasterRepository $coasterRepository,
        private CoasterSummaryService $summaryService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('coaster-id', null, InputOption::VALUE_OPTIONAL, 'Generate summary for specific coaster ID');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force regeneration even if summary exists');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not call Bedrock API');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $coasterId = $input->getOption('coaster-id');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        if ($coasterId) {
            $coaster = $this->coasterRepository->find($coasterId);
            if (!$coaster) {
                $io->error("Coaster with ID {$coasterId} not found");
                return Command::FAILURE;
            }
            $coasters = [$coaster];
        } else {
            $coasters = $this->coasterRepository->findBy(['enabled' => true]);
        }

        $processed = 0;
        $generated = 0;

        foreach ($coasters as $coaster) {
            if (!$force && !$this->summaryService->shouldUpdateSummary($coaster)) {
                continue;
            }

            $io->writeln("Processing: {$coaster->getName()}");
            
            if ($dryRun) {
                $io->writeln("✓ Dry run - no API call made");
            } else {
                $this->processCoaster($coaster, $io, $generated);
            }
            
            $processed++;
            
            // Rate limiting to avoid API throttling
            if (!$dryRun && $processed % 10 === 0) {
                sleep(1);
            }
        }

        $io->success("Processed {$processed} coasters, generated {$generated} summaries");
        return Command::SUCCESS;
    }

    private function processCoaster($coaster, $io, int &$generated): void
    {
        $reviewCount = \count($this->summaryService->getCoasterReviews($coaster));
        
        if ($reviewCount < 20) {
            $io->writeln("⚠ Skipped (insufficient reviews: {$reviewCount}/20)");
            return;
        }
        
        $summary = $this->summaryService->generateSummary($coaster);
        
        if ($summary) {
            $generated++;
            $io->writeln("✓ Generated summary");
        } else {
            $io->writeln("⚠ Failed to generate summary");
        }
    }
}