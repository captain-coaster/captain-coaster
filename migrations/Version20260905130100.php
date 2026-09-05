<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Email notifications are resuming after being off; `email_notification`
 * defaulting to true meant ~91% of accounts were "opted in" purely by
 * inertia, never a real choice. Treat this as a genuine fresh start: flip
 * the default for new signups and reset the existing backlog to opted-out,
 * surfaced via a one-time in-app notice rather than an email announcing it.
 */
final class Version20260905130100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Default email_notification to false, and reset existing users to opted-out';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users CHANGE email_notification email_notification TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE users SET email_notification = 0');
    }

    public function down(Schema $schema): void
    {
        // Loses which accounts had explicitly opted out before this migration ran — acceptable for a rollback path.
        $this->addSql('ALTER TABLE users CHANGE email_notification email_notification TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('UPDATE users SET email_notification = 1');
    }
}
