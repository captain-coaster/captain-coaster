<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AiCallRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:costs',
    description: 'Bedrock calls and spend per task and model, from the ai_call ledger'
)]
class AiCostsCommand extends Command
{
    public function __construct(private readonly AiCallRepository $aiCallRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Period, in days back from now', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $rows = $this->aiCallRepository->costsSince(new \DateTimeImmutable(\sprintf('-%d days', $days)));

        $io->title(\sprintf('AI spend, last %d days', $days));

        if ([] === $rows) {
            $io->note('No AI call recorded over this period.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Task', 'Model', 'Calls', 'Failed', 'Total $', 'Per call $'],
            array_map(static fn (array $row): array => [
                $row['task'],
                $row['model'],
                $row['calls'],
                $row['failures'],
                number_format($row['cost_usd'], 4),
                number_format($row['avg_cost_usd'], 6),
            ], $rows),
        );
        $io->writeln(\sprintf('Total: $%s', number_format(array_sum(array_column($rows, 'cost_usd')), 4)));

        return Command::SUCCESS;
    }
}
