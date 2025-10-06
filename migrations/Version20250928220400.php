<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250928220400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ranking_history DROP FOREIGN KEY FK_2F6B2621216303C');
        $this->addSql('ALTER TABLE ranking_history ADD CONSTRAINT FK_2F6B2621216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ranking_history DROP FOREIGN KEY FK_2F6B2621216303C');
        $this->addSql('ALTER TABLE ranking_history ADD CONSTRAINT FK_2F6B2621216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id)');
    }
}
