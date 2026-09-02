<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reallocate fr/es/de pro.pace selections to pro.speed (they meant speed, not pace)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster_pro rp
            JOIN ridden_coaster rc ON rc.id = rp.ridden_coaster_id
            JOIN tag t_old ON t_old.id = rp.tag_id AND t_old.name = 'pro.pace'
            JOIN tag t_new ON t_new.name = 'pro.speed'
            SET rp.tag_id = t_new.id
            WHERE rc.language IN ('fr', 'es', 'de')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster_pro rp
            JOIN ridden_coaster rc ON rc.id = rp.ridden_coaster_id
            JOIN tag t_old ON t_old.id = rp.tag_id AND t_old.name = 'pro.speed'
            JOIN tag t_new ON t_new.name = 'pro.pace'
            SET rp.tag_id = t_new.id
            WHERE rc.language IN ('fr', 'es', 'de')
            SQL);
    }
}
