<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031125350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_feedback_ratio ON coaster_summary');
        $this->addSql('ALTER TABLE coaster_summary CHANGE positive_votes positive_votes INT NOT NULL, CHANGE negative_votes negative_votes INT NOT NULL, CHANGE feedback_ratio feedback_ratio NUMERIC(5, 4) NOT NULL');
        $this->addSql('ALTER TABLE summary_feedback CHANGE ip_address ip_address VARCHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE coaster_summary CHANGE positive_votes positive_votes INT DEFAULT 0 NOT NULL, CHANGE negative_votes negative_votes INT DEFAULT 0 NOT NULL, CHANGE feedback_ratio feedback_ratio NUMERIC(5, 4) DEFAULT \'0.0000\' NOT NULL');
        $this->addSql('CREATE INDEX idx_feedback_ratio ON coaster_summary (feedback_ratio)');
        $this->addSql('ALTER TABLE summary_feedback CHANGE ip_address ip_address VARCHAR(45) NOT NULL');
    }
}
