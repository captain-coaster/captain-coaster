<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251213134807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update review report reasons to simplified categories and migrate existing data';
    }

    public function up(Schema $schema): void
    {
        // Migrate existing report reasons to new simplified categories
        // Map 'offensive' and 'incorrect' to 'inappropriate'
        // Keep 'inappropriate' and 'spam' as they are
        // Map 'other' to 'spam' (as it's the closest match)
        $this->addSql("UPDATE review_report SET reason = 'inappropriate' WHERE reason IN ('offensive', 'incorrect', 'other')");
    }

    public function down(Schema $schema): void
    {
        // Note: We cannot perfectly revert the reason mapping as we've lost the original data
    }
}
