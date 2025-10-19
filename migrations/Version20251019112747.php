<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019112747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE continent_translation DROP FOREIGN KEY `FK_E1BACE4B2C2AC5D3`');
        $this->addSql('DROP TABLE continent_translation');
        $this->addSql('ALTER TABLE coaster CHANGE formerNames formerNames LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE park CHANGE formerNames formerNames LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE continent_translation (id INT AUTO_INCREMENT NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, locale VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX continent_translation_uniq_trans (translatable_id, locale), INDEX IDX_E1BACE4B2C2AC5D3 (translatable_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE continent_translation ADD CONSTRAINT `FK_E1BACE4B2C2AC5D3` FOREIGN KEY (translatable_id) REFERENCES continent (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coaster CHANGE formerNames formerNames LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\'');
        $this->addSql('ALTER TABLE park CHANGE formerNames formerNames LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\'');
    }
}
