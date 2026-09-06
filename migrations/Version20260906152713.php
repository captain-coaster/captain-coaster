<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906152713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add composite index on ridden_coaster (has_review, language, updated_at) for ReviewController's listing (findAllReviews()), which filters on all three -- the existing (has_review, updated_at) index doesn't cover language, so MariaDB post-filters it row by row for the ~59% of has_review rows that aren't the requested language";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE INDEX idx_ridden_coaster_has_review_language_updated_at ON ridden_coaster (has_review, language, updated_at)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_has_review_language_updated_at ON ridden_coaster');
    }
}
