<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251030180713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SummaryFeedback entity and feedback metrics to CoasterSummary';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE summary_feedback (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(45) NOT NULL, is_positive TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, summary_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_58331D5A2AC2D45C (summary_id), INDEX IDX_58331D5AA76ED395 (user_id), INDEX idx_summary_feedback (summary_id, is_positive), UNIQUE INDEX unique_user_feedback (summary_id, user_id), UNIQUE INDEX unique_anonymous_feedback (summary_id, ip_address), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE summary_feedback ADD CONSTRAINT FK_58331D5A2AC2D45C FOREIGN KEY (summary_id) REFERENCES coaster_summary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE summary_feedback ADD CONSTRAINT FK_58331D5AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE coaster_summary DROP INDEX UNIQ_COASTER_LANGUAGE, ADD INDEX idx_coaster_language (coaster_id, language)');
        $this->addSql('ALTER TABLE coaster_summary CHANGE language language VARCHAR(2) NOT NULL');
        $this->addSql('ALTER TABLE coaster_summary ADD positive_votes INT NOT NULL DEFAULT 0, ADD negative_votes INT NOT NULL DEFAULT 0, ADD feedback_ratio NUMERIC(5, 4) NOT NULL DEFAULT 0.0000');
        $this->addSql('CREATE INDEX idx_feedback_ratio ON coaster_summary (feedback_ratio)');
        $this->addSql('ALTER TABLE coaster_summary RENAME INDEX uniq_8db6713b216303c TO UNIQ_A64A1A1C216303C');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE summary_feedback DROP FOREIGN KEY FK_58331D5A2AC2D45C');
        $this->addSql('ALTER TABLE summary_feedback DROP FOREIGN KEY FK_58331D5AA76ED395');
        $this->addSql('DROP TABLE summary_feedback');
        $this->addSql('ALTER TABLE coaster_summary DROP INDEX idx_coaster_language, ADD UNIQUE INDEX UNIQ_COASTER_LANGUAGE (coaster_id, language)');
        $this->addSql('ALTER TABLE coaster_summary DROP INDEX idx_feedback_ratio');
        $this->addSql('ALTER TABLE coaster_summary DROP positive_votes, DROP negative_votes, DROP feedback_ratio');
        $this->addSql('ALTER TABLE coaster_summary CHANGE language language VARCHAR(2) DEFAULT \'en\' NOT NULL');
        $this->addSql('ALTER TABLE coaster_summary RENAME INDEX uniq_a64a1a1c216303c TO UNIQ_8DB6713B216303C');
    }
}
