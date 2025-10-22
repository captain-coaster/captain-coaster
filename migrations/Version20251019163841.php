<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019163841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create coaster_summary table with language support';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coaster_summary (id INT AUTO_INCREMENT NOT NULL, summary LONGTEXT NOT NULL, dynamic_pros JSON NOT NULL, dynamic_cons JSON NOT NULL, reviews_analyzed INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, coaster_id INT NOT NULL, language VARCHAR(2) NOT NULL DEFAULT \'en\', UNIQUE INDEX UNIQ_COASTER_LANGUAGE (coaster_id, language), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE coaster_summary ADD CONSTRAINT FK_8DB6713B216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE coaster_summary DROP FOREIGN KEY FK_8DB6713B216303C');
        $this->addSql('DROP TABLE coaster_summary');
    }
}
