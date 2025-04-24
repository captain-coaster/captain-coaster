<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250424225449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                CREATE TABLE relocation (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE relocation_coaster (id INT AUTO_INCREMENT NOT NULL, coaster_id INT NOT NULL, relocation_id INT NOT NULL, position INT NOT NULL, INDEX IDX_D823F98D216303C (coaster_id), INDEX IDX_D823F98D8E70437 (relocation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE relocation_coaster ADD CONSTRAINT FK_D823F98D216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE relocation_coaster ADD CONSTRAINT FK_D823F98D8E70437 FOREIGN KEY (relocation_id) REFERENCES relocation (id) ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                ALTER TABLE relocation_coaster DROP FOREIGN KEY FK_D823F98D216303C
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE relocation_coaster DROP FOREIGN KEY FK_D823F98D8E70437
            SQL);
        $this->addSql(<<<'SQL'
                DROP TABLE relocation
            SQL);
        $this->addSql(<<<'SQL'
                DROP TABLE relocation_coaster
            SQL);
    }
}
