<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905194759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index on ridden_coaster.created_at so the homepage "new ratings today" count is not a full table scan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_ridden_coaster_created_at ON ridden_coaster (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_ridden_coaster_created_at ON ridden_coaster');
    }
}
