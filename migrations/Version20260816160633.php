<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260816160633 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moderatedAt to ridden_coaster and AI moderation fields to review_report';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ridden_coaster ADD moderated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE review_report CHANGE user_id user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE review_report ADD ai_confidence VARCHAR(10) DEFAULT NULL, ADD ai_explanation LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ridden_coaster DROP moderated_at');
        $this->addSql('ALTER TABLE review_report DROP ai_confidence, DROP ai_explanation');
        // System-generated (AI) reports have user_id IS NULL by design; they
        // can't be represented once the column is NOT NULL again, so remove
        // them before reverting the column.
        $this->addSql('DELETE FROM review_report WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE review_report CHANGE user_id user_id INT NOT NULL');
    }
}
