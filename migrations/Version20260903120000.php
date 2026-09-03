<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate admin roles to the new Contributor/Moderator/Admin hierarchy: ROLE_SUPER_ADMIN becomes ROLE_ADMIN, former ROLE_ADMIN becomes ROLE_MODERATOR';
    }

    public function up(Schema $schema): void
    {
        // JSON_SEARCH (rather than JSON_CONTAINS) because a few rows have `roles` stored
        // as a JSON object with non-sequential keys instead of a plain array.
        // Single UPDATE with CASE WHEN, checked against the pre-update value, so a row
        // holding both ROLE_ADMIN and ROLE_SUPER_ADMIN isn't collapsed to ROLE_MODERATOR
        // by an earlier statement before the ROLE_SUPER_ADMIN branch can match it.
        $this->addSql(
            'UPDATE users SET roles = CASE '
            .'WHEN JSON_SEARCH(roles, \'one\', \'ROLE_SUPER_ADMIN\') IS NOT NULL THEN \'["ROLE_ADMIN"]\' '
            .'WHEN JSON_SEARCH(roles, \'one\', \'ROLE_ADMIN\') IS NOT NULL THEN \'["ROLE_MODERATOR"]\' '
            .'ELSE roles END '
            .'WHERE JSON_SEARCH(roles, \'one\', \'ROLE_ADMIN\') IS NOT NULL OR JSON_SEARCH(roles, \'one\', \'ROLE_SUPER_ADMIN\') IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE users SET roles = CASE '
            .'WHEN JSON_SEARCH(roles, \'one\', \'ROLE_ADMIN\') IS NOT NULL THEN \'["ROLE_SUPER_ADMIN"]\' '
            .'WHEN JSON_SEARCH(roles, \'one\', \'ROLE_MODERATOR\') IS NOT NULL THEN \'["ROLE_ADMIN"]\' '
            .'ELSE roles END '
            .'WHERE JSON_SEARCH(roles, \'one\', \'ROLE_ADMIN\') IS NOT NULL OR JSON_SEARCH(roles, \'one\', \'ROLE_MODERATOR\') IS NOT NULL'
        );
    }
}
