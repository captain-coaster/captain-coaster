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
        $this->addSql('UPDATE users SET roles = \'["ROLE_MODERATOR"]\' WHERE JSON_SEARCH(roles, \'one\', \'ROLE_ADMIN\') IS NOT NULL');
        $this->addSql('UPDATE users SET roles = \'["ROLE_ADMIN"]\' WHERE JSON_SEARCH(roles, \'one\', \'ROLE_SUPER_ADMIN\') IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE users SET roles = \'["ROLE_SUPER_ADMIN"]\' WHERE JSON_SEARCH(roles, \'one\', \'ROLE_ADMIN\') IS NOT NULL');
        $this->addSql('UPDATE users SET roles = \'["ROLE_ADMIN"]\' WHERE JSON_SEARCH(roles, \'one\', \'ROLE_MODERATOR\') IS NOT NULL');
    }
}
