<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Review policy: reviews an AI report holds for a moderator are published, their report stays in the queue';
    }

    public function up(Schema $schema): void
    {
        // Under the review policy, a review in doubt is shown while a moderator checks it. Reviews
        // a member report waits on stay hidden, as before.
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster SET review_enabled = 1
            WHERE review_enabled = 0
              AND id IN (SELECT review_id FROM review_report WHERE user_id IS NULL AND resolved = 0)
              AND id NOT IN (SELECT review_id FROM review_report WHERE user_id IS NOT NULL AND resolved = 0 AND review_id IS NOT NULL)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster SET review_enabled = 0
            WHERE id IN (SELECT review_id FROM review_report WHERE user_id IS NULL AND resolved = 0)
            SQL);
    }
}
