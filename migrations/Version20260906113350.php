<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906113350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on coaster.updated_at so updatedAt-sorted listings (e.g. the admin coaster/park/image CRUD lists, default-sorted this way) are not a filesort over the whole table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_coaster_updated_at ON coaster (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_coaster_updated_at ON coaster');
    }
}
