<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906115507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on coaster.rank so the default (unfiltered) ranking listing, sorted by rank, is not a filesort over the whole table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_coaster_rank ON coaster (rank)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_coaster_rank ON coaster');
    }
}
