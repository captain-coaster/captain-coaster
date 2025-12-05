<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251204214621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multilingual support: create vocabulary_guide table and add unique constraint on coaster_summary (coaster_id, language)';
    }

    public function up(Schema $schema): void
    {
        // Create vocabulary_guide table with content
        $this->addSql('CREATE TABLE vocabulary_guide (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, content LONGTEXT NOT NULL, reviews_analyzed INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9924CD5AD4DB71B5 (language), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE coaster_summary DROP INDEX idx_coaster_language, ADD UNIQUE INDEX unique_coaster_language (coaster_id, language)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vocabulary_guide');
        $this->addSql('ALTER TABLE coaster_summary DROP INDEX unique_coaster_language, ADD INDEX idx_coaster_language (coaster_id, language)');
    }
}
