<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260125153135 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at field to users table for soft delete functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_users_deleted_at ON users (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_deleted_at ON users');
        $this->addSql('ALTER TABLE users DROP deleted_at');
    }
}
