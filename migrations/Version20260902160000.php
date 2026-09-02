<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add whip and speed review tags, and a con side for pace';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO tag (name, type) VALUES ('pro.whip', 'pro')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('pro.speed', 'pro')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('con.whip', 'con')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('con.speed', 'con')");
        $this->addSql("INSERT INTO tag (name, type) VALUES ('con.pace', 'con')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tag WHERE name IN ('pro.whip', 'pro.speed', 'con.whip', 'con.speed', 'con.pace')");
    }
}
