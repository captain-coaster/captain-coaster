<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906115217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on coaster.openingDate, the new default sort for the search-coaster listing, so it is not a filesort over the whole table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_coaster_opening_date ON coaster (openingDate)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_coaster_opening_date ON coaster');
    }
}
