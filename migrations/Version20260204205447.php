<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204205447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make API key nullable and delete unused API keys';
    }

    public function up(Schema $schema): void
    {
        // Make api_key nullable
        $this->addSql('ALTER TABLE users CHANGE api_key api_key VARCHAR(255) DEFAULT NULL');

        // Delete API keys that have never been used (lastApiKeyUsedAt is NULL)
        $this->addSql('UPDATE users SET api_key = NULL WHERE last_api_key_used_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Generate new API keys for users who don't have one
        $this->addSql('UPDATE users SET api_key = UUID() WHERE api_key IS NULL');
        $this->addSql('ALTER TABLE users CHANGE api_key api_key VARCHAR(255) NOT NULL');
    }
}
