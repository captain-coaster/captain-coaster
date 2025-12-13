<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202205429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Revert display_reviews_in_all_languages to false by default';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users CHANGE display_reviews_in_all_languages display_reviews_in_all_languages TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users CHANGE display_reviews_in_all_languages display_reviews_in_all_languages TINYINT(1) DEFAULT 1 NOT NULL');
    }
}
