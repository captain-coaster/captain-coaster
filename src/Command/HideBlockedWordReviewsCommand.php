<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ReviewReport;
use App\Repository\RiddenCoasterRepository;
use App\Validator\Constraints\BlockedWordList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Hides the published reviews that the word list would refuse today: those from before it, or
 * from before a word was added. Each one gets a resolved auto_hidden report, so moderators can
 * spot-check and publish it again, and its author can edit it. Run again when the list changes.
 */
#[AsCommand(
    name: 'app:reviews:hide-blocked-words',
    description: 'Hide published reviews that contain a blocked word'
)]
class HideBlockedWordReviewsCommand extends Command
{
    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the reviews without hiding them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $found = [];
        foreach ($this->riddenCoasterRepository->streamPublishedReviewTexts() as ['id' => $id, 'review' => $text]) {
            if (null !== $word = BlockedWordList::find($text)) {
                $found[$id] = $word;
            }
        }

        $rows = [];
        foreach ($found as $id => $word) {
            $review = $this->riddenCoasterRepository->find($id);
            if (null === $review) {
                continue;
            }
            $rows[] = [$id, $word, mb_strimwidth((string) $review->getReview(), 0, 80, '…')];
            if ($dryRun) {
                continue;
            }

            $review->setReviewEnabled(false);
            // Analyzed: the hourly app:analyze-reviews run must not send it back to the AI.
            $review->setAnalyzedAt(new \DateTime());
            $report = ReviewReport::fromAi($review, ReviewReport::REASON_BLOCKED_WORD, \sprintf('Contains "%s", refused by the word list since.', $word));
            $report->setStatus(ReviewReport::STATUS_AUTO_HIDDEN);
            $this->entityManager->persist($report);
        }

        if (!$dryRun && [] !== $rows) {
            $this->entityManager->flush();
        }

        if ([] !== $rows) {
            $io->table(['review', 'word', 'text'], $rows);
        }
        $io->success(\sprintf($dryRun ? '%d review(s) would be hidden.' : '%d review(s) hidden.', \count($rows)));

        return Command::SUCCESS;
    }
}
