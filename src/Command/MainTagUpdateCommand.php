<?php

namespace App\Command;

use App\Entity\Coaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class MainTagUpdateCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * MainTagUpdateCommand constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('main-tag:update')
            ->setDescription('Update main tags for all coasters');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('main-tag');
        $output->writeln('Start updating main tags');

        $conn = $this->em->getConnection();
        $coasters = $this->em->getRepository(Coaster::class)->findAll();

        $sql = 'truncate table main_tag';
        $stmt = $conn->prepare($sql);
        $stmt->execute();

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

            $stmt = $conn->prepare($sql);
            $stmt->execute(['coasterId' => $coaster->getId()]);

            $rank = 1;
            foreach ($stmt->fetchAll() as $mainTag) {
                // stop after 3 main tags, or if it's not popular enough
                if ($rank > 3 || $mainTag['nb'] < 3) {
                    break;
                }

                $sql = 'INSERT INTO `main_tag` (coaster_id, tag_id, rank) VALUES (:coasterId, :tagId, :rank)';
                $stmt = $conn->prepare($sql);
                $stmt->execute(['coasterId' => $coaster->getId(), 'tagId' => (int)$mainTag['id'], 'rank' => $rank]);
                $rank++;
            }
        }

        $output->writeln((string)$stopwatch->stop('main-tag'));
    }
}
