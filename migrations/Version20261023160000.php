<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coasters\' main images leave out disabled members\' photos, which EnabledUserFilter hides';
    }

    public function up(Schema $schema): void
    {
        // Same query as ImageManager::setMainImages()
        $this->addSql('UPDATE coaster c
            LEFT JOIN (
                SELECT DISTINCT image.coaster_id,
                       FIRST_VALUE(image.id) OVER (PARTITION BY image.coaster_id ORDER BY image.like_counter DESC, image.updated_at DESC) as id
                FROM image
                JOIN users u ON u.id = image.uploader_id
                WHERE image.enabled = 1 AND u.enabled = 1
            ) i ON i.coaster_id = c.id
            SET c.main_image_id = i.id');
    }

    public function down(Schema $schema): void
    {
        // Data only: the previous main images are recomputed by the next image change
    }
}
