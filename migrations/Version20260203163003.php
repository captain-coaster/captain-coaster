<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203163003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reviewer_id column to review_report table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_report ADD reviewer_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_report DROP reviewer_id');
    }
}
