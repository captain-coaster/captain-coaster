<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190419133717 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE liste_coaster DROP FOREIGN KEY FK_470BB229E85441D8');
        $this->addSql('DROP INDEX IDX_470BB229E85441D8 ON liste_coaster');
        $this->addSql('ALTER TABLE liste_coaster CHANGE liste_id top_id INT NOT NULL');
        $this->addSql('ALTER TABLE liste_coaster ADD CONSTRAINT FK_470BB229C82CB256 FOREIGN KEY (top_id) REFERENCES liste (id)');
        $this->addSql('CREATE INDEX IDX_470BB229C82CB256 ON liste_coaster (top_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE liste_coaster DROP FOREIGN KEY FK_470BB229C82CB256');
        $this->addSql('DROP INDEX IDX_470BB229C82CB256 ON liste_coaster');
        $this->addSql('ALTER TABLE liste_coaster CHANGE top_id liste_id INT NOT NULL');
        $this->addSql('ALTER TABLE liste_coaster ADD CONSTRAINT FK_470BB229E85441D8 FOREIGN KEY (liste_id) REFERENCES liste (id)');
        $this->addSql('CREATE INDEX IDX_470BB229E85441D8 ON liste_coaster (liste_id)');
    }
}
