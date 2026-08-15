<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315120200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create wishlist table with unique constraint on (user, coaster)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wishlist (id INT AUTO_INCREMENT NOT NULL, coaster_id INT NOT NULL, user_id INT NOT NULL, added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9CE12A31E4E5250E (coaster_id), INDEX IDX_9CE12A31A76ED395 (user_id), UNIQUE INDEX user_coaster_wishlist_unique (coaster_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31E4E5250E FOREIGN KEY (coaster_id) REFERENCES coaster (id)');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY FK_9CE12A31E4E5250E');
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY FK_9CE12A31A76ED395');
        $this->addSql('DROP TABLE wishlist');
    }
}
