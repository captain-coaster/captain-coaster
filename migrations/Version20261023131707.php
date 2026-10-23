<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023131707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A member report carries the reporter\'s comment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_report ADD reporter_comment LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE review_report DROP reporter_comment');
    }
}
