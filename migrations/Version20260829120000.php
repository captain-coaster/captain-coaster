<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace user.imperial boolean with user.preferred_units string (metric|imperial)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD preferred_units VARCHAR(20) DEFAULT 'metric' NOT NULL");
        $this->addSql("UPDATE users SET preferred_units = 'imperial' WHERE imperial = 1");
        $this->addSql('ALTER TABLE users DROP imperial');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD imperial TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE users SET imperial = 1 WHERE preferred_units = 'imperial'");
        $this->addSql('ALTER TABLE users DROP preferred_units');
    }
}
