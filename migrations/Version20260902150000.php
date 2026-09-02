<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add family-friendly, brakes and laterals review tags';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO tag (name, type) VALUES ('pro.familyfriendly', 'pro')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('pro.laterals', 'pro')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('con.brakes', 'con')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('con.laterals', 'con')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tag WHERE name IN ('pro.familyfriendly', 'pro.laterals', 'con.brakes', 'con.laterals')");
    }
}
