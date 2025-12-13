<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251213135419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add snapshot fields to review_report to preserve data when reviews are deleted';
    }

    public function up(Schema $schema): void
    {
        // Update the foreign key constraint to allow null and set null on delete
        $this->addSql('ALTER TABLE review_report DROP FOREIGN KEY `FK_4E5930443E2E969B`');
        $this->addSql('ALTER TABLE review_report ADD review_content LONGTEXT DEFAULT NULL, ADD coaster_name VARCHAR(255) DEFAULT NULL, ADD reviewer_name VARCHAR(255) DEFAULT NULL, CHANGE review_id review_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE review_report ADD CONSTRAINT FK_4E5930443E2E969B FOREIGN KEY (review_id) REFERENCES ridden_coaster (id) ON DELETE SET NULL');

        // Populate snapshot data for existing reports
        $this->addSql('
            UPDATE review_report rr 
            JOIN ridden_coaster rc ON rr.review_id = rc.id 
            JOIN coaster c ON rc.coaster_id = c.id 
            JOIN users u ON rc.user_id = u.id 
            SET 
                rr.review_content = rc.review,
                rr.coaster_name = c.name,
                rr.reviewer_name = u.display_name
            WHERE rr.review_content IS NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review_report DROP FOREIGN KEY FK_4E5930443E2E969B');
        $this->addSql('ALTER TABLE review_report DROP review_content, DROP coaster_name, DROP reviewer_name, CHANGE review_id review_id INT NOT NULL');
        $this->addSql('ALTER TABLE review_report ADD CONSTRAINT `FK_4E5930443E2E969B` FOREIGN KEY (review_id) REFERENCES ridden_coaster (id) ON DELETE CASCADE');
    }
}
