<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'users.preferred_review_languages: rewrite the values stored as a JSON object ({"1":"fr"}) as lists (#445).';
    }

    public function up(Schema $schema): void
    {
        // Normalized in PHP rather than with JSON SQL functions, which differ between MariaDB and PostgreSQL
        $rows = $this->connection->fetchAllKeyValue('SELECT id, preferred_review_languages FROM users WHERE preferred_review_languages LIKE \'{%\'');

        foreach ($rows as $id => $json) {
            $languages = array_values(json_decode((string) $json, true, flags: \JSON_THROW_ON_ERROR));

            $this->addSql('UPDATE users SET preferred_review_languages = ? WHERE id = ?', [json_encode($languages, \JSON_THROW_ON_ERROR), $id]);
        }
    }

    public function down(Schema $schema): void
    {
    }
}
