<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260123192004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean up user authentication: add banned_at, remove is_verified and facebook fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD banned_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users DROP is_verified');
        $this->addSql('ALTER TABLE users DROP facebook_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP banned_at');
        $this->addSql('ALTER TABLE users ADD is_verified TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE users ADD facebook_id VARCHAR(255) DEFAULT NULL UNIQUE');
    }
}
