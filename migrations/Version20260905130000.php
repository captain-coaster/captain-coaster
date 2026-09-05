<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Splits `notification` (message/parameter/type/created_at duplicated per
 * recipient) into shared content (`notification`, columns dropped down to
 * just the content) + per-user delivery/read state (`notification_recipient`,
 * new). Existing rows are carried forward 1:1 as their own recipient row —
 * no attempt to retroactively deduplicate historical broadcasts.
 */
final class Version20260905130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split notification into shared content + per-recipient notification_recipient';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notification_recipient (
                id INT AUTO_INCREMENT NOT NULL,
                notification_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                is_read TINYINT(1) NOT NULL,
                read_at DATETIME DEFAULT NULL,
                INDEX IDX_CEEDBC16EF1A9D84 (notification_id),
                INDEX idx_notification_recipient_user_created (user_id, created_at),
                INDEX idx_notification_recipient_user_unread (user_id, is_read),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE notification_recipient
            ADD CONSTRAINT FK_notification_recipient_notification FOREIGN KEY (notification_id) REFERENCES notification (id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE notification_recipient
            ADD CONSTRAINT FK_notification_recipient_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipient (notification_id, user_id, created_at, is_read, read_at)
            SELECT id, user_id, created_at, is_read, NULL FROM notification
            SQL);

        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('DROP INDEX idx_notification_user_unread ON notification');
        $this->addSql('ALTER TABLE notification DROP user_id, DROP is_read, CHANGE type type VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD user_id INT DEFAULT NULL, ADD is_read TINYINT(1) DEFAULT NULL, CHANGE type type VARCHAR(255) NOT NULL');

        // Content rows with no surviving recipient (already purged by
        // app:notification:purge) have no user_id/is_read to restore and
        // can't satisfy the NOT NULL below — the old schema has no
        // equivalent row to roll back to, so drop them.
        $this->addSql(<<<'SQL'
            DELETE FROM notification
            WHERE id NOT IN (SELECT DISTINCT notification_id FROM notification_recipient)
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE notification n
            INNER JOIN notification_recipient nr ON nr.notification_id = n.id
            SET n.user_id = nr.user_id, n.is_read = nr.is_read
            SQL);

        $this->addSql('ALTER TABLE notification CHANGE user_id user_id INT NOT NULL, CHANGE is_read is_read TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_notification_user_unread ON notification (user_id, is_read, created_at)');

        $this->addSql('DROP TABLE notification_recipient');
    }
}
