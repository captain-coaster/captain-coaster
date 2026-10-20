<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Review moderation aligned with photos: review_enabled, moderated_at renamed analyzed_at, report statuses approved and rejected';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_moderated_at ON ridden_coaster');
        // Added published (DEFAULT 1, no table rewrite), then the two small sets below are hidden.
        $this->addSql('ALTER TABLE ridden_coaster ADD review_enabled TINYINT DEFAULT 1 NOT NULL, CHANGE moderated_at analyzed_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ridden_coaster_review_enabled_analyzed_at ON ridden_coaster (review_enabled, analyzed_at)');

        // Everything stays published but two sets: reviews an AI report holds for a moderator, and
        // reviews written since AI moderation started (2026-08-16) that it never reached, which
        // app:analyze-reviews sends back to it. Older reviews were never meant to be analyzed.
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster SET review_enabled = 0
            WHERE id IN (SELECT review_id FROM review_report WHERE user_id IS NULL AND resolved = 0)
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster SET review_enabled = 0
            WHERE analyzed_at IS NULL AND review IS NOT NULL AND TRIM(review) != '' AND updated_at >= '2026-08-16'
            SQL);
        $this->addSql('ALTER TABLE ridden_coaster ALTER review_enabled SET DEFAULT 0');

        $this->addSql("UPDATE review_report SET status = 'approved' WHERE status = 'no_action'");
        $this->addSql("UPDATE review_report SET status = 'rejected' WHERE status = 'review_deleted'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE review_report SET status = 'no_action' WHERE status = 'approved'");
        $this->addSql("UPDATE review_report SET status = 'review_deleted' WHERE status = 'rejected'");
        $this->addSql("UPDATE review_report SET reason = 'other' WHERE reason IN ('uncertain', 'analysis_failed')");

        $this->addSql('DROP INDEX idx_ridden_coaster_review_enabled_analyzed_at ON ridden_coaster');
        $this->addSql('ALTER TABLE ridden_coaster DROP review_enabled, CHANGE analyzed_at moderated_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ridden_coaster_moderated_at ON ridden_coaster (moderated_at)');
    }
}
