<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop add_today_date_when_rating column from users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN add_today_date_when_rating');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN add_today_date_when_rating TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
