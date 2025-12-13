<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251213144319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status field to review_report table to track specific actions taken';
    }

    public function up(Schema $schema): void
    {
        // Add status column with default value
        $this->addSql('ALTER TABLE review_report ADD status VARCHAR(20) NOT NULL DEFAULT \'pending\'');

        // Set status based on existing resolved field
        $this->addSql('UPDATE review_report SET status = \'no_action\' WHERE resolved = 1');
        $this->addSql('UPDATE review_report SET status = \'pending\' WHERE resolved = 0');

        $this->addSql('ALTER TABLE vocabulary_guide RENAME INDEX uniq_9924cd5ad4db71b5 TO UNIQ_F81D4ADFD4DB71B5');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review_report DROP status');
        $this->addSql('ALTER TABLE vocabulary_guide RENAME INDEX uniq_f81d4adfd4db71b5 TO UNIQ_9924CD5AD4DB71B5');
    }
}
