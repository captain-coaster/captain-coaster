<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace user.display_reviews_in_all_languages boolean with user.preferred_review_languages JSON array';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD preferred_review_languages JSON NOT NULL');
        // Preserve the explicit "show everything" choice for users who had
        // it on; everyone else (the default) gets an empty array, which
        // means "only my own language" -- a deliberate behavior change
        // from the old default of "mine first, others still visible".
        $this->addSql('UPDATE users SET preferred_review_languages = \'["en","fr","es","de"]\' WHERE display_reviews_in_all_languages = 1');
        $this->addSql('UPDATE users SET preferred_review_languages = \'[]\' WHERE display_reviews_in_all_languages = 0');
        $this->addSql('ALTER TABLE users DROP display_reviews_in_all_languages');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD display_reviews_in_all_languages TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE users SET display_reviews_in_all_languages = 1 WHERE JSON_LENGTH(preferred_review_languages) > 0');
        $this->addSql('ALTER TABLE users DROP preferred_review_languages');
    }
}
