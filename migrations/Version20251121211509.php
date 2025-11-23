<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121211509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cached upvote counter and remove unused like/dislike columns';
    }

    public function up(Schema $schema): void
    {
        // Remove self-upvoted reviews (where user upvoted their own review)
        $this->addSql('
            DELETE ru FROM review_upvote ru
            INNER JOIN ridden_coaster rc ON ru.review_id = rc.id
            WHERE ru.user_id = rc.user_id
        ');

        // Add the upvote_counter column with default value 0
        $this->addSql('ALTER TABLE ridden_coaster ADD upvote_counter INT NOT NULL DEFAULT 0');

        // Populate the counter with existing upvote counts (after removing self-upvotes)
        $this->addSql('
            UPDATE ridden_coaster rc
            LEFT JOIN (
                SELECT review_id, COUNT(*) as count
                FROM review_upvote
                GROUP BY review_id
            ) ru ON rc.id = ru.review_id
            SET rc.upvote_counter = COALESCE(ru.count, 0)
        ');

        // Remove unused like and dislike columns
        $this->addSql('ALTER TABLE ridden_coaster DROP likes, DROP dislike');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ridden_coaster DROP upvote_counter');
        $this->addSql('ALTER TABLE ridden_coaster ADD likes INT DEFAULT NULL, ADD dislike INT DEFAULT NULL');
    }
}
