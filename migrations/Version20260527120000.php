<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Unify list types and fold the wishlist into a bucket-list Top.
 *
 * - liste.type becomes ranking|bucket|custom (drops top/flop and the `main` flag).
 * - Each user's wishlist becomes a single type='bucket' Top, ordered by added_at.
 * - The wishlist table is dropped.
 */
final class Version20260527120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unify Top types (ranking/bucket/custom), migrate wishlist into bucket lists, drop main + wishlist table';
    }

    public function up(Schema $schema): void
    {
        // 1. Re-type existing lists. main=1 was the ranking list; everything else (old top/flop) becomes custom.
        $this->addSql("UPDATE liste SET type = 'ranking' WHERE main = 1");
        $this->addSql("UPDATE liste SET type = 'custom' WHERE main = 0");

        // 2. One bucket list per user who has wishlist entries.
        //    The `main` column is dropped at the end of this migration; supply 0 until then.
        $this->addSql("
            INSERT INTO liste (name, type, main, user_id, created_at, updated_at)
            SELECT 'Bucket list', 'bucket', 0, w.user_id, NOW(), NOW()
            FROM wishlist w
            GROUP BY w.user_id
        ");

        // 3. Wishlist entries become bucket TopCoasters, positioned by added_at order.
        $this->addSql("
            INSERT INTO liste_coaster (top_id, coaster_id, position)
            SELECT l.id, w.coaster_id, ROW_NUMBER() OVER (PARTITION BY w.user_id ORDER BY w.added_at, w.id)
            FROM wishlist w
            JOIN liste l ON l.user_id = w.user_id AND l.type = 'bucket'
        ");

        // 4. Drop the now-redundant wishlist table and the main flag.
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY FK_9CE12A31E4E5250E');
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY FK_9CE12A31A76ED395');
        $this->addSql('DROP TABLE wishlist');
        $this->addSql('ALTER TABLE liste DROP main');
    }

    public function down(Schema $schema): void
    {
        // Recreate the main flag and mark the ranking list.
        $this->addSql('ALTER TABLE liste ADD main TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql("UPDATE liste SET main = 1 WHERE type = 'ranking'");

        // Recreate the wishlist table (see Version20260315120200).
        $this->addSql('CREATE TABLE wishlist (id INT AUTO_INCREMENT NOT NULL, coaster_id INT NOT NULL, user_id INT NOT NULL, added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9CE12A31E4E5250E (coaster_id), INDEX IDX_9CE12A31A76ED395 (user_id), UNIQUE INDEX user_coaster_wishlist_unique (coaster_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31E4E5250E FOREIGN KEY (coaster_id) REFERENCES coaster (id)');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // Restore wishlist rows from the bucket lists, then remove the bucket lists.
        $this->addSql("
            INSERT INTO wishlist (coaster_id, user_id, added_at)
            SELECT lc.coaster_id, l.user_id, NOW()
            FROM liste_coaster lc
            JOIN liste l ON l.id = lc.top_id
            WHERE l.type = 'bucket'
        ");
        $this->addSql("DELETE lc FROM liste_coaster lc JOIN liste l ON l.id = lc.top_id WHERE l.type = 'bucket'");
        $this->addSql("DELETE FROM liste WHERE type = 'bucket'");

        // Best-effort type restore: the old top/flop distinction was merged into custom and cannot be recovered.
        $this->addSql("UPDATE liste SET type = 'top' WHERE type IN ('ranking', 'custom')");
    }
}
