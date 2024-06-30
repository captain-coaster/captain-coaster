<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240630075001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE status ADD `order` INT NOT NULL');
        $this->addSql('UPDATE status SET `order` = 1 WHERE name = "status.announced"');
        $this->addSql('UPDATE status SET `order` = 2 WHERE name = "status.soft.opening"');
        $this->addSql('UPDATE status SET `order` = 3 WHERE name = "status.construction"');
        $this->addSql('UPDATE status SET `order` = 4 WHERE name = "status.operating"');
        $this->addSql('UPDATE status SET `order` = 5 WHERE name = "status.closed.temporarily"');
        $this->addSql('UPDATE status SET `order` = 6 WHERE name = "status.retracked"');
        $this->addSql('UPDATE status SET `order` = 7 WHERE name = "status.relocated"');
        $this->addSql('UPDATE status SET `order` = 8 WHERE name = "status.closed.definitely"');
        $this->addSql('UPDATE status SET `order` = 9 WHERE name = "status.rumored"');
        $this->addSql('UPDATE status SET `order` = 10 WHERE name = "status.unknown"');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE status DROP `order`');
    }
}
