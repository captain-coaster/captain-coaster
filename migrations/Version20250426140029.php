<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250426140029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                ALTER TABLE model ADD manufacturer_id INT DEFAULT NULL
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE model ADD CONSTRAINT FK_D79572D9A23B42D FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE INDEX IDX_D79572D9A23B42D ON model (manufacturer_id)
            SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
                ALTER TABLE model DROP FOREIGN KEY FK_D79572D9A23B42D
            SQL);
        $this->addSql(<<<'SQL'
                DROP INDEX IDX_D79572D9A23B42D ON model
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE model DROP manufacturer_id
            SQL);
    }
}
