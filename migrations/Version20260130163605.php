<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130163605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove CASCADE from image.uploader_id FK to let Doctrine handle cascade via events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY `FK_C53D045F16678C77`');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT FK_C53D045F16678C77 FOREIGN KEY (uploader_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_C53D045F16678C77');
        $this->addSql('ALTER TABLE image ADD CONSTRAINT `FK_C53D045F16678C77` FOREIGN KEY (uploader_id) REFERENCES users (id) ON DELETE CASCADE');
    }
}
