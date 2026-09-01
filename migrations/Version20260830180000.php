<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index notification(user_id, is_read, created_at) for the navbar unread lookup';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_notification_user_unread ON notification (user_id, is_read, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_notification_user_unread ON notification');
    }
}
