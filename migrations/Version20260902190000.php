<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_model column and indexes to coaster_summary, rename updated_at to regenerated_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coaster_summary ADD ai_model VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE coaster_summary CHANGE updated_at regenerated_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_coaster_summary_language_negative_votes ON coaster_summary (language, negative_votes)');
        $this->addSql('CREATE INDEX idx_coaster_summary_language_ai_model ON coaster_summary (language, ai_model)');

        // Rough backfill for existing summaries generated before this column existed -
        // approximates historical model usage per language, not exact per-row truth.
        $this->addSql("UPDATE coaster_summary SET ai_model = 'gpt-5.6-luna' WHERE language = 'fr' AND ai_model IS NULL");
        $this->addSql("UPDATE coaster_summary SET ai_model = 'gpt-oss-120b' WHERE language = 'en' AND ai_model IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_coaster_summary_language_negative_votes ON coaster_summary');
        $this->addSql('DROP INDEX idx_coaster_summary_language_ai_model ON coaster_summary');
        $this->addSql('ALTER TABLE coaster_summary CHANGE regenerated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE coaster_summary DROP ai_model');
    }
}
