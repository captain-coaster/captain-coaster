<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add floater review tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO tag (name, type) VALUES ('pro.floater', 'pro')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tag WHERE name = 'pro.floater'");
    }
}
