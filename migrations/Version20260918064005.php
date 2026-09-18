<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918064005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GenAI photo moderation + focal-point crop (#398): adds image.focal_x/focal_y '
            .'(DB is the golden source for the crop Lambda\'s S3-metadata copy, so a resync '
            .'never needs re-running Bedrock) and image.analyzed_at (null = never analyzed -- '
            .'the reprocess/backfill command\'s default target, and also what excludes an image '
            .'from ever silently auto-publishing: the old 23h ValidatePicturesCommand timer is '
            .'gone, a clean result enables an image immediately, a flagged one waits on a human '
            .'indefinitely). New image_report table is the review queue for flagged images, '
            .'mirroring review_report\'s shape (status/ai_confidence/ai_explanation) but with a '
            .'JSON categories array instead of a single reason column, since one photo can '
            .'trigger multiple checks at once (e.g. watermark and people-as-subject together). '
            .'image_id is nullable with ON DELETE SET NULL so a report survives a Reject '
            .'action\'s hard-delete of the photo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE image_report (id INT AUTO_INCREMENT NOT NULL, image_filename VARCHAR(255) DEFAULT NULL, coaster_name VARCHAR(255) DEFAULT NULL, categories JSON NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, resolved TINYINT NOT NULL, ai_confidence VARCHAR(10) DEFAULT NULL, ai_explanation LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, image_id INT DEFAULT NULL, INDEX IDX_6B32294C3DA5256D (image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE image_report ADD CONSTRAINT FK_6B32294C3DA5256D FOREIGN KEY (image_id) REFERENCES image (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE image ADD focal_x DOUBLE PRECISION DEFAULT NULL, ADD focal_y DOUBLE PRECISION DEFAULT NULL, ADD analyzed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image_report DROP FOREIGN KEY FK_6B32294C3DA5256D');
        $this->addSql('DROP TABLE image_report');
        $this->addSql('ALTER TABLE image DROP focal_x, DROP focal_y, DROP analyzed_at');
    }
}
