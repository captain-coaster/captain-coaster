<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816191357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on ridden_coaster.moderated_at, the moderation queue\'s own filter column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_ridden_coaster_moderated_at ON ridden_coaster (moderated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_moderated_at ON ridden_coaster');
    }
}
