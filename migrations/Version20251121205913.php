<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251121205913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove self-voted pictures';
    }

    public function up(Schema $schema): void
    {
        // Remove all likes where the user is the uploader of the image
        $this->addSql('
            DELETE li FROM liked_image li
            INNER JOIN image i ON li.image_id = i.id
            WHERE li.user_id = i.uploader_id
        ');

        // Recount the like_counter for all images
        $this->addSql('
            UPDATE image i
            SET i.like_counter = (
                SELECT COUNT(*)
                FROM liked_image li
                WHERE li.image_id = i.id
            )
        ');
    }

    public function down(Schema $schema): void
    {
        // Cannot restore self-voted pictures
    }
}
