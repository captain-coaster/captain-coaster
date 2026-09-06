<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260906184455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add plain index on ridden_coaster.updated_at -- DefaultController's homepage query (getLatestRatings()) sorts by updated_at with no other filter on this table, so the existing has_review-led composite indexes can't help; MariaDB was falling back to a full table scan + filesort (826k rows), spilling to an on-disk temp table on every cache miss. Confirmed as the cause of the 'disk full'/'table file is corrupted' errors seen right after each of today's deploys, when cache:clear wipes the Redis result-cache pool and several homepage requests race to recompute this at once.";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_ridden_coaster_updated_at ON ridden_coaster (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_updated_at ON ridden_coaster');
    }
}
