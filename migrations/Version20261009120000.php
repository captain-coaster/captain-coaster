<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Badge feature removed: deletes the badge notifications and drops badge and user_badge';
    }

    public function up(Schema $schema): void
    {
        // notification_recipient rows go with them (ON DELETE CASCADE).
        $this->addSql("DELETE FROM notification WHERE type = 'badge'");
        $this->addSql('DROP TABLE user_badge');
        $this->addSql('DROP TABLE badge');
    }

    /** Recreates the empty tables: who earned what and the badge notifications are not restored. */
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE badge (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL,
                filename_fr VARCHAR(255) NOT NULL,
                filename_en VARCHAR(255) NOT NULL,
                UNIQUE INDEX UNIQ_FEF0481D437FF474 (filename_fr),
                UNIQUE INDEX UNIQ_FEF0481D7C53FBF8 (filename_en),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_badge (
                user_id INT NOT NULL,
                badge_id INT NOT NULL,
                INDEX IDX_1C32B345A76ED395 (user_id),
                INDEX IDX_1C32B345F7A2C2FC (badge_id),
                PRIMARY KEY (user_id, badge_id),
                CONSTRAINT FK_1C32B345A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT FK_1C32B345F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
            SQL);
    }
}
