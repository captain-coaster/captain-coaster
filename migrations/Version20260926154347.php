<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926154347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coaster best rank (#451): best_rank/best_rank_at, backfilled from ranking_history (earliest month the best rank was reached).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coaster ADD best_rank INT DEFAULT NULL, ADD best_rank_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE coaster c JOIN (SELECT coaster_id, MIN(`rank`) AS best FROM ranking_history GROUP BY coaster_id) b ON b.coaster_id = c.id SET c.best_rank = b.best');
        $this->addSql('UPDATE coaster c SET c.best_rank_at = (SELECT MIN(r.computed_at) FROM ranking_history rh JOIN ranking r ON r.id = rh.ranking_id WHERE rh.coaster_id = c.id AND rh.`rank` = c.best_rank) WHERE c.best_rank IS NOT NULL');
        // Current ranks newer than the last history snapshot
        $this->addSql('UPDATE coaster c SET c.best_rank = c.`rank`, c.best_rank_at = (SELECT MAX(computed_at) FROM ranking) WHERE c.`rank` IS NOT NULL AND (c.best_rank IS NULL OR c.`rank` < c.best_rank)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coaster DROP best_rank, DROP best_rank_at');
    }
}
