<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'image.hash widened to a SHA-256 hex digest; existing CRC32 values kept until backfilled.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image CHANGE hash hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE image SET hash = NULL WHERE LENGTH(hash) > 8');
        $this->addSql('ALTER TABLE image CHANGE hash hash VARCHAR(8) DEFAULT NULL');
    }
}
