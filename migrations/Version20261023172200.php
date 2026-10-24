<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023172200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A rating keeps a fingerprint of the IP address it was sent from';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ridden_coaster ADD ip_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_ridden_coaster_ip_hash ON ridden_coaster (ip_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_ip_hash ON ridden_coaster');
        $this->addSql('ALTER TABLE ridden_coaster DROP ip_hash');
    }
}
