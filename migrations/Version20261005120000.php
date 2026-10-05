<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo content revision: adds image.rev (null until an original is replaced), part of the v2 picture URL hash';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD rev INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP rev');
    }
}
