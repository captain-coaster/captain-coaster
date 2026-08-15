<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make rating nullable, rename ridden_at to first_ridden_at, add last_ridden_at and ride_count columns to ridden_coaster table';
    }

    public function up(Schema $schema): void
    {
        // Make rating nullable
        $this->addSql('ALTER TABLE ridden_coaster MODIFY COLUMN rating FLOAT NULL');
        // Rename ridden_at to first_ridden_at
        $this->addSql('ALTER TABLE ridden_coaster RENAME COLUMN ridden_at TO first_ridden_at');
        // Add last_ridden_at column
        $this->addSql('ALTER TABLE ridden_coaster ADD COLUMN last_ridden_at DATE NULL');
        // Add ride_count column with default value
        $this->addSql('ALTER TABLE ridden_coaster ADD COLUMN ride_count INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        // Remove ride_count column
        $this->addSql('ALTER TABLE ridden_coaster DROP COLUMN ride_count');
        // Remove last_ridden_at column
        $this->addSql('ALTER TABLE ridden_coaster DROP COLUMN last_ridden_at');
        // Rename first_ridden_at back to ridden_at
        $this->addSql('ALTER TABLE ridden_coaster RENAME COLUMN first_ridden_at TO ridden_at');
        // Make rating not nullable again
        $this->addSql('ALTER TABLE ridden_coaster MODIFY COLUMN rating FLOAT NOT NULL');
    }
}
