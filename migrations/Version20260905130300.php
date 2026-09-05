<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * One-time in-app announcement for the email_notification reset in
 * Version20260905130100, replacing what would otherwise be a bespoke
 * dismissible banner — this reuses the notification system itself instead of
 * inventing a second, parallel "seen it" mechanism.
 */
final class Version20260905130300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Broadcast a one-time announcement notification for the email_notification reset';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO notification (message, parameter, created_at, type)
            VALUES ('notif.announcement.emailDefaultChanged', NULL, NOW(), 'announcement')
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipient (notification_id, user_id, created_at, is_read, read_at)
            SELECT LAST_INSERT_ID(), id, NOW(), 0, NULL FROM users
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE nr FROM notification_recipient nr
            INNER JOIN notification n ON n.id = nr.notification_id
            WHERE n.type = 'announcement' AND n.message = 'notif.announcement.emailDefaultChanged'
            SQL);
        $this->addSql("DELETE FROM notification WHERE type = 'announcement' AND message = 'notif.announcement.emailDefaultChanged'");
    }
}
