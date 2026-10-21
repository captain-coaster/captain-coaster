<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Message\AnalyzeReviewMessage;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Moderation\ReviewModerationService;
use App\Service\Moderation\ReviewVerdict;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Hourly recovery for review moderation, which runs on its own when a review is saved
 * (AnalyzeReviewMessage): sends the reviews it never reached back to it. --text and --eval call
 * the model without saving anything, to try the policy.
 */
#[AsCommand(
    name: 'app:analyze-reviews',
    description: 'Send reviews moderation never analyzed back to it'
)]
class AnalyzeReviewsCommand extends Command
{
    /** What a golden set expects of each text, from the rule the model answers. */
    private const OUTCOMES = ['publish', 'review', 'hide'];

    public function __construct(
        private RiddenCoasterRepository $riddenCoasterRepository,
        private ReviewModerationService $moderationService,
        private MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max number of reviews to send', 500)
            ->addOption('text', null, InputOption::VALUE_REQUIRED, 'Analyze one ad-hoc text, not saved')
            ->addOption('rating', null, InputOption::VALUE_REQUIRED, 'Rating to use with --text', '3.0')
            ->addOption('coaster-name', null, InputOption::VALUE_REQUIRED, 'Coaster name to use with --text', 'Test Coaster')
            ->addOption('eval', null, InputOption::VALUE_REQUIRED, 'Analyze a golden set (TSV with expected, rating, coaster and text columns; expected is publish, review or hide) and print the disagreements')
            ->setHelp(
                'Sends the reviews moderation never analyzed to the async worker.'."\n\n".
                'Examples:'."\n".
                '  php bin/console app:analyze-reviews'."\n".
                '  php bin/console app:analyze-reviews --text="this ride sucks" --rating=1'."\n".
                '  php bin/console app:analyze-reviews --eval=var/moderation/review-golden.tsv'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null !== $text = $input->getOption('text')) {
            $review = $this->adHocReview((string) $text, (float) $input->getOption('rating'), (string) $input->getOption('coaster-name'));
            try {
                $verdict = $this->moderationService->analyze($review);
            } catch (AiCallFailed $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
            $io->writeln(\sprintf('%s (%s, %s)%s', $verdict->rule->value, self::outcome($verdict), $verdict->language, $verdict->explanation ? ': '.$verdict->explanation : ''));

            return Command::SUCCESS;
        }

        if (null !== $file = $input->getOption('eval')) {
            return $this->evaluate($io, (string) $file);
        }

        $reviews = $this->riddenCoasterRepository->findPendingAnalysis((int) $input->getOption('limit'));
        foreach ($reviews as $review) {
            $this->messageBus->dispatch(new AnalyzeReviewMessage($review->getId()));
        }

        $io->success(\sprintf('Sent %d review(s) to moderation.', \count($reviews)));

        return Command::SUCCESS;
    }

    private function evaluate(SymfonyStyle $io, string $file): int
    {
        $lines = @file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        if (false === $lines || [] === $lines) {
            $io->error("Cannot read {$file}.");

            return Command::FAILURE;
        }

        // Plain tab-separated lines, as `mariadb -B` writes them: no quoting.
        $header = explode("\t", array_shift($lines));
        $cases = [];
        foreach ($lines as $line) {
            $row = explode("\t", $line);
            if (\count($row) === \count($header)) {
                $cases[] = array_combine($header, $row);
            }
        }

        $counts = array_fill_keys(self::OUTCOMES, array_fill_keys([...self::OUTCOMES, 'failed'], 0));
        $disagreements = [];
        foreach ($cases as $case) {
            $expected = $case['expected'] ?? '';
            if (!\in_array($expected, self::OUTCOMES, true)) {
                continue;
            }

            try {
                $verdict = $this->moderationService->analyze($this->adHocReview($case['text'], (float) $case['rating'], $case['coaster']));
                $outcome = self::outcome($verdict);
            } catch (AiCallFailed) {
                $verdict = null;
                $outcome = 'failed';
            }

            ++$counts[$expected][$outcome];
            if ($outcome !== $expected) {
                $disagreements[] = [$expected, $outcome, $verdict?->rule->value ?? '—', mb_strimwidth($case['text'], 0, 90, '…'), $verdict->explanation ?? ''];
            }
        }

        $io->table(['expected \\ got', ...self::OUTCOMES, 'failed'], array_map(
            static fn (string $expected, array $row): array => [$expected, ...array_values($row)],
            array_keys($counts),
            $counts,
        ));

        $wronglyHidden = $counts['publish']['hide'] + $counts['review']['hide'];
        $missed = $counts['hide']['publish'];
        $io->writeln(\sprintf('Wrongly hidden: %d. Missed hides: %d. Disagreements: %d of %d.', $wronglyHidden, $missed, \count($disagreements), array_sum(array_map(array_sum(...), $counts))));

        if ([] !== $disagreements) {
            $io->table(['expected', 'got', 'rule', 'text', 'explanation'], $disagreements);
        }

        return Command::SUCCESS;
    }

    private static function outcome(ReviewVerdict $verdict): string
    {
        return match (true) {
            $verdict->rule->hides() => 'hide',
            'none' === $verdict->rule->value => 'publish',
            default => 'review',
        };
    }

    private function adHocReview(string $text, float $rating, string $coasterName): RiddenCoaster
    {
        $coaster = new Coaster();
        $coaster->setName($coasterName);

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue($rating);
        $review->setReview($text);

        return $review;
    }
}
