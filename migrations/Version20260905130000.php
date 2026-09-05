<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Splits `notification` (message/parameter/type/created_at duplicated per
 * recipient) into shared content (`notification`, columns dropped down to
 * just the content) + per-user delivery/read state (`notification_recipient`,
 * new). Historical rows with identical (type, message, parameter) — e.g.
 * every "rating1 badge" notification, regardless of which user earned it —
 * are collapsed onto one canonical content row per group; each recipient
 * still keeps its own original created_at/is_read.
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

        // Map every old row to the lowest id among rows sharing its (type,
        // message, parameter) — its new canonical content row — rather than
        // carrying the duplication forward. `<=>` is used for parameter since
        // regular `=` never matches NULL to NULL.
        $this->addSql(<<<'SQL'
            INSERT INTO notification_recipient (notification_id, user_id, created_at, is_read, read_at)
            SELECT canonical.canonical_id, n.user_id, n.created_at, n.is_read, NULL
            FROM notification n
            INNER JOIN (
                SELECT type, message, parameter, MIN(id) AS canonical_id
                FROM notification
                GROUP BY type, message, parameter
            ) canonical
                ON canonical.type = n.type
                AND canonical.message = n.message
                AND canonical.parameter <=> n.parameter
            SQL);

        // Now that every recipient points at its group's canonical row, the
        // rest of each duplicate group is redundant.
        $this->addSql(<<<'SQL'
            DELETE FROM notification
            WHERE id NOT IN (
                SELECT * FROM (
                    SELECT MIN(id) FROM notification GROUP BY type, message, parameter
                ) AS keepers
            )
            SQL);

        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('DROP INDEX idx_notification_user_unread ON notification');
        $this->addSql('ALTER TABLE notification DROP user_id, DROP is_read, CHANGE type type VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD user_id INT DEFAULT NULL, ADD is_read TINYINT(1) DEFAULT NULL, CHANGE type type VARCHAR(255) NOT NULL');

        // Expands each recipient back into its own notification row (the old
        // schema's duplication) as a new row — exact historical ids aren't
        // referenced anywhere outside this table, so new auto-increment ids
        // are an acceptable loss for a rollback path.
        $this->addSql(<<<'SQL'
            INSERT INTO notification (message, parameter, created_at, type, user_id, is_read)
            SELECT n.message, n.parameter, nr.created_at, n.type, nr.user_id, nr.is_read
            FROM notification_recipient nr
            INNER JOIN notification n ON n.id = nr.notification_id
            SQL);

        // The canonical rows (still NULL user_id/is_read) are now represented
        // by the expanded per-recipient rows inserted above.
        $this->addSql('DELETE FROM notification WHERE user_id IS NULL');

        $this->addSql('ALTER TABLE notification CHANGE user_id user_id INT NOT NULL, CHANGE is_read is_read TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_notification_user_unread ON notification (user_id, is_read, created_at)');

        $this->addSql('DROP TABLE notification_recipient');
    }
}
