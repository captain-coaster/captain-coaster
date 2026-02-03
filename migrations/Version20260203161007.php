<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203161007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rating_value column to review_report table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_report ADD rating_value DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_report DROP rating_value');
    }
}
