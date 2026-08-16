<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use App\Service\ReviewModerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Analyzes reviews for moderation flags (toxic/spam/troll/offtopic/other) and
 * detects their language using AWS Bedrock.
 *
 * v1: calibration-only (--sample / --text). No database writes yet — that
 * lands once the calibration pass on this version is approved.
 */
#[AsCommand(
    name: 'app:analyze-reviews',
    description: 'Analyze reviews for moderation flags and language detection using AWS Bedrock'
)]
class AnalyzeReviewsCommand extends Command
{
    public function __construct(
        private RiddenCoasterRepository $riddenCoasterRepository,
        private ReviewModerationService $moderationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('sample', null, InputOption::VALUE_REQUIRED, 'Calibration mode: analyze N random reviews with text and print results (no persistence)')
            ->addOption('text', null, InputOption::VALUE_REQUIRED, 'Calibration mode: analyze one ad-hoc review text (not persisted), for testing hand-picked examples')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating to use with --text', '3.0')
            ->addOption('coaster-name', null, InputOption::VALUE_REQUIRED, 'Coaster name to use with --text', 'Test Coaster')
            ->setHelp(
                'Calibration tool: analyzes reviews and prints the LLM verdicts, without writing anything to the database.'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:analyze-reviews --sample=20'."\n".
                '  php bin/console app:analyze-reviews --text="i fucking hate this coaster" --rating=1'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $text = $input->getOption('text');
        if (null !== $text) {
            $coaster = new Coaster();
            $coaster->setName((string) $input->getOption('coaster-name'));

            $review = new RiddenCoaster();
            $review->setCoaster($coaster);
            $review->setValue((float) $input->getOption('rating'));
            $review->setReview((string) $text);

            $this->printResult($io, $review, 'ad-hoc');

            return Command::SUCCESS;
        }

        $sample = $input->getOption('sample');
        if (null === $sample) {
            $io->error('Pass --sample=N or --text="..." to run a calibration check.');

            return Command::FAILURE;
        }

        $reviews = $this->riddenCoasterRepository->findRandomReviewsWithText((int) $sample);
        $io->note(\sprintf('Analyzing %d random review(s) (dry-run, no persistence)...', \count($reviews)));

        foreach ($reviews as $review) {
            $this->printResult($io, $review, (string) $review->getId());
        }

        $io->success(\sprintf('Analyzed %d review(s).', \count($reviews)));

        return Command::SUCCESS;
    }

    private function printResult(SymfonyStyle $io, RiddenCoaster $review, string $label): void
    {
        $result = $this->moderationService->analyze($review);

        if (null === $result) {
            $io->writeln("  ⚠ Review {$label}: analysis failed");

            return;
        }

        $io->writeln(\sprintf(
            '  Review %s: language=%s category=%s confidence=%s',
            $label,
            $result['language'],
            $result['category'],
            $result['confidence'] ?? 'n/a'
        ));
        $io->writeln('    Text: '.substr($review->getReview() ?? '', 0, 120));

        if ('ok' !== $result['category'] && $result['explanation']) {
            $io->writeln("    → {$result['explanation']}");
        }
    }
}
