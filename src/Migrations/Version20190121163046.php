<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190121163046 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE coaster_type DROP FOREIGN KEY FK_B0990AFAC54C8C93');
        $this->addSql('DROP TABLE coaster_type');
        $this->addSql('DROP TABLE report');
        $this->addSql('DROP TABLE type');
        $this->addSql('ALTER TABLE users ADD add_today_date_when_rating TINYINT(1) DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE coaster_type (coaster_id INT NOT NULL, type_id INT NOT NULL, INDEX IDX_B0990AFA216303C (coaster_id), INDEX IDX_B0990AFAC54C8C93 (type_id), PRIMARY KEY(coaster_id, type_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE report (id INT AUTO_INCREMENT NOT NULL, park_id INT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, slug VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, language VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, visitDate DATETIME NOT NULL, content LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci, cover VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, viewsNumber INT NOT NULL, status VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, creationDate DATETIME NOT NULL, updateDate DATETIME NOT NULL, INDEX IDX_C42F7784A76ED395 (user_id), INDEX IDX_C42F778444990C25 (park_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, slug VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, UNIQUE INDEX UNIQ_8CDE5729989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE coaster_type ADD CONSTRAINT FK_B0990AFA216303C FOREIGN KEY (coaster_id) REFERENCES coaster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coaster_type ADD CONSTRAINT FK_B0990AFAC54C8C93 FOREIGN KEY (type_id) REFERENCES type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F778444990C25 FOREIGN KEY (park_id) REFERENCES park (id)');
        $this->addSql('ALTER TABLE report ADD CONSTRAINT FK_C42F7784A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users DROP add_today_date_when_rating');
    }
}
