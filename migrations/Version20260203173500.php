<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203173500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill bannedAt for legacy disabled users without bannedAt or deletedAt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE users 
            SET banned_at = '2026-01-01 01:00:00' 
            WHERE enabled = 0 
            AND banned_at IS NULL 
            AND deleted_at IS NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE users 
            SET banned_at = NULL 
            WHERE banned_at = '2026-01-01 01:00:00'
        ");
    }
}
