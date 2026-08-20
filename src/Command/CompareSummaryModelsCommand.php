<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Service\CoasterSummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for evaluating a candidate AI model against the current
 * default by generating both summaries for the same coaster/language and
 * printing them side by side. Never persists anything - safe to run against
 * production data to spot quality regressions before switching models.
 */
#[AsCommand(
    name: 'app:compare-summary-models',
    description: 'Preview AI summaries from two models for the same coaster, side by side, without persisting anything'
)]
class CompareSummaryModelsCommand extends Command
{
    public function __construct(
        private CoasterRepository $coasterRepository,
        private CoasterSummaryRepository $coasterSummaryRepository,
        private CoasterSummaryService $summaryService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('coaster-id', InputArgument::REQUIRED, 'Coaster ID to preview a summary for')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Target language (en, fr, es, de)', 'en')
            ->addOption('candidate-model', 'c', InputOption::VALUE_REQUIRED, 'Candidate model key to evaluate', 'gpt-5.6-luna')
            ->addOption('baseline-model', 'b', InputOption::VALUE_OPTIONAL, 'Baseline model key (defaults to the model normally used for this language)', null)
            ->addOption('no-vocab-guide', null, InputOption::VALUE_NONE, 'Skip the vocabulary guide prompt section for both previews, to evaluate whether it still helps')
            ->setHelp(
                "Generates two preview summaries (baseline vs candidate model) for the same coaster and language,\n".
                "without saving either one. Also shows the currently stored summary and its user feedback for context.\n\n".
                'Example: php bin/console app:compare-summary-models 32 --language=fr --candidate-model=gpt-5.6-luna'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $coaster = $this->coasterRepository->find((int) $input->getArgument('coaster-id'));
        if (!$coaster) {
            $io->error('Coaster not found.');

            return Command::FAILURE;
        }

        $language = (string) $input->getOption('language');
        $candidateModel = (string) $input->getOption('candidate-model');
        $baselineModel = $input->getOption('baseline-model');
        $baselineModel = null !== $baselineModel ? (string) $baselineModel : null;
        $includeVocabularyGuide = !$input->getOption('no-vocab-guide');

        $io->title(\sprintf('%s (#%d) — %s', $coaster->getName(), $coaster->getId(), strtoupper($language)));

        $storedSummary = $this->coasterSummaryRepository->findByCoasterAndLanguage($coaster, $language);
        if ($storedSummary) {
            $io->section('Currently stored summary');
            $io->text($storedSummary->getSummary());
            $io->writeln(\sprintf(
                'Pros: %s | Cons: %s | 👍 %d / 👎 %d | Reviews analyzed: %d',
                implode(', ', $storedSummary->getDynamicPros()) ?: '—',
                implode(', ', $storedSummary->getDynamicCons()) ?: '—',
                $storedSummary->getPositiveVotes(),
                $storedSummary->getNegativeVotes(),
                $storedSummary->getReviewsAnalyzed()
            ));
        } else {
            $io->note('No stored summary for this coaster/language yet.');
        }

        $io->section(\sprintf('Baseline (%s)%s', $baselineModel ?? 'service default', $includeVocabularyGuide ? '' : ' — no vocab guide'));
        $this->renderPreview($io, $this->summaryService->previewSummary($coaster, $baselineModel, $language, $includeVocabularyGuide));

        $io->section(\sprintf('Candidate (%s)%s', $candidateModel, $includeVocabularyGuide ? '' : ' — no vocab guide'));
        $this->renderPreview($io, $this->summaryService->previewSummary($coaster, $candidateModel, $language, $includeVocabularyGuide));

        return Command::SUCCESS;
    }

    /** @param array<string, mixed> $preview */
    private function renderPreview(SymfonyStyle $io, array $preview): void
    {
        if (empty($preview['summary'])) {
            $io->warning(\sprintf('No preview generated (%s)', $preview['reason'] ?? 'unknown reason'));

            return;
        }

        $io->text($preview['summary']);
        $io->writeln(\sprintf(
            'Pros: %s | Cons: %s',
            implode(', ', $preview['pros']) ?: '—',
            implode(', ', $preview['cons']) ?: '—'
        ));

        $metadata = $preview['metadata'] ?? [];
        $reviewCount = $preview['review_count'] ?? 0;
        $totalReviewCount = $preview['total_review_count'] ?? $reviewCount;
        $reviewsLabel = $totalReviewCount > $reviewCount
            ? \sprintf('%d same-language + %d backfilled = %d total', $reviewCount, $totalReviewCount - $reviewCount, $totalReviewCount)
            : (string) $reviewCount;

        $io->writeln(\sprintf(
            'Model: %s | Reviews analyzed: %s | Latency: %sms | Tokens: %s+%s | Cost: $%s',
            $preview['model_key'] ?? $metadata['model'] ?? 'unknown',
            $reviewsLabel,
            $metadata['latency_ms'] ?? '?',
            $metadata['input_tokens'] ?? '?',
            $metadata['output_tokens'] ?? '?',
            isset($metadata['cost_usd']) ? number_format((float) $metadata['cost_usd'], 4) : '?'
        ));
    }
}
