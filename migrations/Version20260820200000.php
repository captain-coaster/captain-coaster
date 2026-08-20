<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop vocabulary_guide table - measured to have no effect on gpt-5.6-luna output quality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE vocabulary_guide');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vocabulary_guide (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, content LONGTEXT NOT NULL, reviews_analyzed INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F81D4ADFD4DB71B5 (language), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}
