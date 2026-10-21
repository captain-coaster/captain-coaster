<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use App\Service\Summary\CoasterSummaryGenerator;
use App\Service\Summary\NotEnoughReviews;
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
        private CoasterSummaryGenerator $summaryGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('coaster-id', InputArgument::REQUIRED, 'Coaster ID to preview a summary for')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Target language (en, fr, es, de)', 'en')
            ->addOption('candidate-model', 'c', InputOption::VALUE_REQUIRED, \sprintf('Candidate model key to evaluate (%s)', implode(', ', array_column(AiModel::cases(), 'value'))), AiModel::Gpt6Luna->value)
            ->addOption('baseline-model', 'b', InputOption::VALUE_OPTIONAL, 'Baseline model key (defaults to the model summaries currently run on)', null)
            ->setHelp(
                "Generates two preview summaries (baseline vs candidate model) for the same coaster and language,\n".
                "without saving either one. Also shows the currently stored summary and its user feedback for context.\n\n".
                'Example: php bin/console app:compare-summary-models 32 --language=fr --candidate-model=gpt-6-luna'
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
        $candidateModel = AiModel::tryFrom((string) $input->getOption('candidate-model'));
        $baselineOption = $input->getOption('baseline-model');
        $baselineModel = null !== $baselineOption ? AiModel::tryFrom((string) $baselineOption) : AiTask::CoasterSummary->model();
        if (null === $candidateModel || null === $baselineModel) {
            $io->error(\sprintf('Unknown model. Available models: %s', implode(', ', array_column(AiModel::cases(), 'value'))));

            return Command::FAILURE;
        }

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

        $io->section(\sprintf('Baseline (%s)', $baselineModel->value));
        $this->renderPreview($io, $coaster, $language, $baselineModel);

        $io->section(\sprintf('Candidate (%s)', $candidateModel->value));
        $this->renderPreview($io, $coaster, $language, $candidateModel);

        return Command::SUCCESS;
    }

    private function renderPreview(SymfonyStyle $io, Coaster $coaster, string $language, AiModel $model): void
    {
        try {
            $preview = $this->summaryGenerator->generate($coaster, $language, $model, preview: true);
        } catch (NotEnoughReviews|AiCallFailed $e) {
            $io->warning(\sprintf('No preview generated: %s', $e->getMessage()));

            return;
        }

        $io->text($preview->summary);
        $io->writeln(\sprintf('Meta description (%d chars): %s', mb_strlen($preview->metaDescription ?? ''), $preview->metaDescription ?? '—'));
        $io->writeln(\sprintf('Pros: %s | Cons: %s', implode(', ', $preview->pros) ?: '—', implode(', ', $preview->cons) ?: '—'));

        $reviews = $preview->reviewsSent > $preview->reviewsAnalyzed
            ? \sprintf('%d same-language + %d backfilled = %d total', $preview->reviewsAnalyzed, $preview->reviewsSent - $preview->reviewsAnalyzed, $preview->reviewsSent)
            : (string) $preview->reviewsAnalyzed;
        $io->writeln(\sprintf('Reviews analyzed: %s | %s', $reviews, $preview->ai->usage()));
    }
}
