<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Review policy: reviews the old AI held for a moderator are judged again under the policy';
    }

    public function up(Schema $schema): void
    {
        // Held under the old rules: their AI report is superseded and the review goes back to the
        // hourly app:analyze-reviews run, still hidden. Failed analyses, and reviews a member
        // report waits on, stay in the queue as they are.
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster SET analyzed_at = NULL
            WHERE review_enabled = 0
              AND id IN (SELECT review_id FROM review_report WHERE user_id IS NULL AND resolved = 0 AND reason != 'analysis_failed')
              AND id NOT IN (SELECT review_id FROM review_report WHERE user_id IS NOT NULL AND resolved = 0 AND review_id IS NOT NULL)
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE review_report SET status = 'superseded', resolved = 1, resolved_at = NOW()
            WHERE user_id IS NULL AND resolved = 0 AND reason != 'analysis_failed'
              AND review_id IN (SELECT id FROM ridden_coaster WHERE review_enabled = 0 AND analyzed_at IS NULL)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
