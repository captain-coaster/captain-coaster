<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'main-tag:update',
    description: 'Update main tags for all coasters.',
    hidden: false,
)]
class MainTagUpdateCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CoasterRepository $coasterRepository)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('main-tag');
        $output->writeln('Start updating main tags');

        $conn = $this->em->getConnection();
        $coasters = $this->coasterRepository->findAll();

        $sql = 'truncate table main_tag';
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        foreach ($coasters as $coaster) {
            $conn = $this->em->getConnection();

            /** @noinspection SqlDialectInspection */
            $sql = 'SELECT t.id, count(*) AS nb FROM ridden_coaster r
            INNER JOIN ridden_coaster_con rc ON rc.ridden_coaster_id = r.id
            INNER JOIN tag t ON t.id = rc.tag_id
            WHERE coaster_id = :coasterId
            GROUP BY t.id
            UNION
            SELECT t.id, count(*) AS nb FROM ridden_coaster r
            INNER JOIN ridden_coaster_pro rp ON rp.ridden_coaster_id = r.id
            INNER JOIN tag t ON t.id = rp.tag_id
            WHERE coaster_id = :coasterId
            GROUP BY t.id
            ORDER BY nb DESC';

            $statement = $conn->prepare($sql);
            $result = $statement->executeQuery(['coasterId' => $coaster->getId()]);

            $rank = 1;
            foreach ($result->fetchAllAssociative() as $mainTag) {
                // stop after 3 main tags, or if it's not popular enough
                if ($rank > 3 || $mainTag['nb'] < 3) {
                    break;
                }

                $sql = 'INSERT INTO `main_tag` (coaster_id, tag_id, rank) VALUES (:coasterId, :tagId, :rank)';
                $stmt = $conn->prepare($sql);
                $stmt->executeStatement(['coasterId' => $coaster->getId(), 'tagId' => (int) $mainTag['id'], 'rank' => $rank]);
                ++$rank;
            }
        }

        $output->writeln((string) $stopwatch->stop('main-tag'));

        return Command::SUCCESS;
    }
}
