<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260312220711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE status ADD color VARCHAR(15) DEFAULT \'neutral\' NOT NULL');
        $this->addSql('UPDATE status SET color = \'red\' WHERE name = \'status.closed.definitely\'');
        $this->addSql('UPDATE status SET color = \'red\' WHERE name = \'status.relocated\'');
        $this->addSql('UPDATE status SET color = \'red\' WHERE name = \'status.retracked\'');
        $this->addSql('UPDATE status SET color = \'purple\' WHERE name = \'status.announced\'');
        $this->addSql('UPDATE status SET color = \'purple\' WHERE name = \'status.construction\'');
        $this->addSql('UPDATE status SET color = \'purple\' WHERE name = \'status.soft.opening\'');
        $this->addSql('UPDATE status SET color = \'green\' WHERE name = \'status.operating\'');
        $this->addSql('UPDATE status SET color = \'yellow\' WHERE name = \'status.closed.temporarily\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE status DROP color');
    }
}
