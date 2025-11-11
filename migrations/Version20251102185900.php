<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add search performance indexes for coaster, park, and user tables.
 */
final class Version20251102185900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add search performance indexes on name and slug fields for coaster, park, and users tables';
    }

    public function up(Schema $schema): void
    {
        // Add indexes for coaster table search performance
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_coaster_name_search ON coaster (name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_coaster_slug_search ON coaster (slug)');

        // Add indexes for park table search performance
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_park_name_search ON park (name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_park_slug_search ON park (slug)');

        // Add indexes for users table search performance
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_users_displayname_search ON users (display_name)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_users_slug_search ON users (slug)');

        // Add composite index for enabled users search
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_users_enabled_displayname ON users (enabled, display_name)');
    }

    public function down(Schema $schema): void
    {
        // Remove search indexes
        $this->addSql('DROP INDEX IF EXISTS idx_coaster_name_search ON coaster');
        $this->addSql('DROP INDEX IF EXISTS idx_coaster_slug_search ON coaster');
        $this->addSql('DROP INDEX IF EXISTS idx_park_name_search ON park');
        $this->addSql('DROP INDEX IF EXISTS idx_park_slug_search ON park');
        $this->addSql('DROP INDEX IF EXISTS idx_users_displayname_search ON users');
        $this->addSql('DROP INDEX IF EXISTS idx_users_slug_search ON users');
        $this->addSql('DROP INDEX IF EXISTS idx_users_enabled_displayname ON users');
    }
}
