<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coaster summary meta description';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coaster_summary ADD meta_description VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coaster_summary DROP meta_description');
    }
}
