<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop main_tag table, unused since AI coaster summaries replaced it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE main_tag');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE main_tag (id INT AUTO_INCREMENT NOT NULL, coaster_id INT DEFAULT NULL, tag_id INT DEFAULT NULL, `rank` INT NOT NULL, INDEX IDX_ED28FA65DA5D5241 (coaster_id), INDEX IDX_ED28FA65BAD26311 (tag_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE main_tag ADD CONSTRAINT FK_ED28FA65DA5D5241 FOREIGN KEY (coaster_id) REFERENCES coaster (id)');
        $this->addSql('ALTER TABLE main_tag ADD CONSTRAINT FK_ED28FA65BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id)');
    }
}
