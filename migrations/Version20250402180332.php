<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250402180332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review_report (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, review_id INT NOT NULL, reason VARCHAR(20) NOT NULL, resolved TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, INDEX IDX_4E593044A76ED395 (user_id), INDEX IDX_4E5930443E2E969B (review_id), UNIQUE INDEX user_review_report_unique (user_id, review_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE review_upvote (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, review_id INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_E2DDC0A6A76ED395 (user_id), INDEX IDX_E2DDC0A63E2E969B (review_id), UNIQUE INDEX user_review_unique (user_id, review_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review_report ADD CONSTRAINT FK_4E593044A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_report ADD CONSTRAINT FK_4E5930443E2E969B FOREIGN KEY (review_id) REFERENCES ridden_coaster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_upvote ADD CONSTRAINT FK_E2DDC0A6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE review_upvote ADD CONSTRAINT FK_E2DDC0A63E2E969B FOREIGN KEY (review_id) REFERENCES ridden_coaster (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ridden_coaster ADD score DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE review_report DROP FOREIGN KEY FK_4E593044A76ED395');
        $this->addSql('ALTER TABLE review_report DROP FOREIGN KEY FK_4E5930443E2E969B');
        $this->addSql('ALTER TABLE review_upvote DROP FOREIGN KEY FK_E2DDC0A6A76ED395');
        $this->addSql('ALTER TABLE review_upvote DROP FOREIGN KEY FK_E2DDC0A63E2E969B');
        $this->addSql('DROP TABLE review_report');
        $this->addSql('DROP TABLE review_upvote');
        $this->addSql('ALTER TABLE ridden_coaster DROP score');
    }
}
