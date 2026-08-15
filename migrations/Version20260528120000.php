<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lists overhaul — public bucket, custom-list visibility, cover image, likes.
 *
 * - liste.is_public BOOL DEFAULT 1 (custom lists only honour it; ranking/bucket are always public).
 * - liste.cover_coaster_id nullable FK → coaster.id (hero image for custom lists, falls back to position-1).
 * - liste.likes_count INT DEFAULT 0 (denormalised counter, kept in sync by listener).
 * - liste_like table (user, top, created_at) with unique (user_id, top_id).
 */
final class Version20260528120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lists overhaul: is_public, cover_coaster_id, likes_count + liste_like table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE liste ADD is_public TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE liste ADD cover_coaster_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE liste ADD likes_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE liste ADD CONSTRAINT FK_LISTE_COVER_COASTER FOREIGN KEY (cover_coaster_id) REFERENCES coaster (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_LISTE_COVER_COASTER ON liste (cover_coaster_id)');

        $this->addSql('CREATE TABLE liste_like (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            top_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_LISTE_LIKE_USER (user_id),
            INDEX IDX_LISTE_LIKE_TOP (top_id),
            UNIQUE INDEX UNIQ_LISTE_LIKE_USER_TOP (user_id, top_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE liste_like ADD CONSTRAINT FK_LISTE_LIKE_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE liste_like ADD CONSTRAINT FK_LISTE_LIKE_TOP FOREIGN KEY (top_id) REFERENCES liste (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE liste_like DROP FOREIGN KEY FK_LISTE_LIKE_USER');
        $this->addSql('ALTER TABLE liste_like DROP FOREIGN KEY FK_LISTE_LIKE_TOP');
        $this->addSql('DROP TABLE liste_like');

        $this->addSql('ALTER TABLE liste DROP FOREIGN KEY FK_LISTE_COVER_COASTER');
        $this->addSql('DROP INDEX IDX_LISTE_COVER_COASTER ON liste');
        $this->addSql('ALTER TABLE liste DROP cover_coaster_id');
        $this->addSql('ALTER TABLE liste DROP is_public');
        $this->addSql('ALTER TABLE liste DROP likes_count');
    }
}
