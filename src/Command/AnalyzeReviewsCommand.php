<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Repository\ReviewReportRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\ReviewModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
 * By default, processes reviews created or edited in the last --since
 * minutes and persists moderatedAt/language plus a ReviewReport for any
 * flagged review. --sample/--text remain dry-run calibration modes.
 */
#[AsCommand(
    name: 'app:analyze-reviews',
    description: 'Analyze reviews for moderation flags and language detection using AWS Bedrock'
)]
class AnalyzeReviewsCommand extends Command
{
    /**
     * $moderationLogger is injected via Symfony/MonologBundle's parameter-name
     * channel autowiring: a constructor argument named "$moderationLogger"
     * resolves to the "moderation" monolog channel (see
     * config/packages/monolog.yaml), which has its own handler outside the
     * main fingers_crossed buffer so these error-level records reach disk
     * in prod instead of being silently discarded.
     */
    public function __construct(
        private RiddenCoasterRepository $riddenCoasterRepository,
        private ReviewModerationService $moderationService,
        private ReviewReportRepository $reviewReportRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $moderationLogger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max number of reviews to process', 50)
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only process reviews created/edited in the last N minutes', 60)
            ->addOption('all', null, InputOption::VALUE_NONE, 'Ignore --since and process the full backlog (explicit backfill mode)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print results without writing to the database')
            ->addOption('sample', null, InputOption::VALUE_REQUIRED, 'Calibration mode: analyze N random reviews with text and print results (no persistence)')
            ->addOption('text', null, InputOption::VALUE_REQUIRED, 'Calibration mode: analyze one ad-hoc review text (not persisted), for testing hand-picked examples')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating to use with --text', '3.0')
            ->addOption('coaster-name', null, InputOption::VALUE_REQUIRED, 'Coaster name to use with --text', 'Test Coaster')
            ->setHelp(
                'Analyzes reviews for moderation flags and detects their language using AWS Bedrock.'."\n".
                'By default, only processes reviews created or edited in the last --since minutes.'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:analyze-reviews'."\n".
                '  php bin/console app:analyze-reviews --limit=200 --since=120'."\n".
                '  php bin/console app:analyze-reviews --all --limit=500 --dry-run'."\n".
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
        if (null !== $sample) {
            $reviews = $this->riddenCoasterRepository->findRandomReviewsWithText((int) $sample);
            $io->note(\sprintf('Analyzing %d random review(s) (dry-run, no persistence)...', \count($reviews)));

            foreach ($reviews as $review) {
                $this->printResult($io, $review, (string) $review->getId());
            }

            $io->success(\sprintf('Analyzed %d review(s).', \count($reviews)));

            return Command::SUCCESS;
        }

        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');
        $all = (bool) $input->getOption('all');
        $since = $all ? null : new \DateTimeImmutable('-'.((int) $input->getOption('since')).' minutes');

        $reviews = $this->riddenCoasterRepository->findPendingAnalysis($since, $limit);
        $io->note(\sprintf(
            'Processing %d pending review(s)%s.',
            \count($reviews),
            $all ? ' (full backlog)' : ' (since '.$since->format('Y-m-d H:i:s').')'
        ));

        $processed = 0;
        $flagged = 0;

        foreach ($reviews as $review) {
            $result = $this->moderationService->analyze($review);

            if (null === $result) {
                $io->writeln("  ⚠ Review {$review->getId()}: analysis failed, skipping (will retry next run)");
                continue;
            }

            $io->writeln(\sprintf(
                '  Review %d: language=%s category=%s confidence=%s',
                $review->getId(),
                $result['language'],
                $result['category'],
                $result['confidence'] ?? 'n/a'
            ));

            ++$processed;

            if ($dryRun) {
                continue;
            }

            $isFlagged = false;

            try {
                $review->setLanguage($result['language']);
                $review->setModeratedAt(new \DateTime());
                $this->entityManager->persist($review);

                if ('ok' !== $result['category']) {
                    if ($this->reviewReportRepository->hasUnresolvedAiReport($review)) {
                        $io->writeln('    → already has a pending AI report for this review, skipping duplicate');
                    } else {
                        $report = new ReviewReport();
                        $report->setReview($review);
                        $report->setUser(null);
                        $report->setReason($result['category']);
                        $report->setReviewContent($review->getReview());
                        $report->setCoasterName($review->getCoaster()->getName());
                        $report->setReviewerName($review->getUser()->getDisplayName());
                        $report->setReviewerId($review->getUser()->getId());
                        $report->setRatingValue($review->getValue());
                        $report->setAiConfidence($result['confidence']);
                        $report->setAiExplanation($result['explanation']);
                        $this->entityManager->persist($report);
                        $isFlagged = true;
                    }
                }

                $this->entityManager->flush();

                if ($isFlagged) {
                    ++$flagged;
                }
            } catch (\Throwable $e) {
                $this->moderationLogger->error('Failed to persist moderation result for review', [
                    'review_id' => $review->getId(),
                    'exception' => $e->getMessage(),
                ]);
                $io->writeln("  ⚠ Review {$review->getId()}: failed to save moderation result, skipping (will retry next run)");
                $this->entityManager->clear();
                continue;
            }
        }

        $io->success(\sprintf('Analyzed %d review(s), %d flagged.', $processed, $flagged));

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
