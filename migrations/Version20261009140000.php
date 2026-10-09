<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ride dates: clears the ones stored before the validation rule that are in the future, before 1950, or after the closing and equal to the day the rating was created';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE ridden_coaster SET ridden_at = NULL WHERE ridden_at > CURRENT_DATE OR ridden_at < '1950-01-01'");

        // After the closing and equal to the creation day: written by "add today's date when rating", not a real ride.
        $this->addSql(<<<'SQL'
            UPDATE ridden_coaster r
            JOIN coaster c ON c.id = r.coaster_id
            SET r.ridden_at = NULL
            WHERE r.ridden_at = DATE(r.created_at)
              AND r.ridden_at > CASE c.closingDatePrecision
                  WHEN 'year' THEN MAKEDATE(YEAR(c.closingDate), 1) + INTERVAL 1 YEAR - INTERVAL 1 DAY
                  WHEN 'month' THEN LAST_DAY(c.closingDate)
                  ELSE c.closingDate
              END
            SQL);
    }

    public function down(Schema $schema): void
    {
        // The cleared dates are not kept anywhere.
        $this->throwIrreversibleMigrationException();
    }
}
