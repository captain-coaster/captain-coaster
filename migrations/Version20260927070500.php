<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927070500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ranking featured head-to-head (#451), shown on the learn-more page; filled from the next computation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ranking ADD featured_duel JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ranking DROP featured_duel');
    }
}
