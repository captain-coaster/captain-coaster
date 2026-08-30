<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ridden_coaster.has_review (generated from review IS NOT NULL) with a supporting index, so latest/all-reviews feeds can use an index instead of a full table scan + filesort';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ridden_coaster ADD has_review TINYINT(1) GENERATED ALWAYS AS (review IS NOT NULL) VIRTUAL');
        $this->addSql('CREATE INDEX idx_ridden_coaster_has_review_updated_at ON ridden_coaster (has_review, updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_has_review_updated_at ON ridden_coaster');
        $this->addSql('ALTER TABLE ridden_coaster DROP has_review');
    }
}
