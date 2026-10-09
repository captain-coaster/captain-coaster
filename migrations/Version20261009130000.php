<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Partial coaster dates: a precision (day, month, year) beside the opening and closing dates; a January 1st was the year-only placeholder';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE coaster ADD openingDatePrecision VARCHAR(5) DEFAULT 'day' NOT NULL, ADD closingDatePrecision VARCHAR(5) DEFAULT 'day' NOT NULL");
        $this->addSql("UPDATE coaster SET openingDatePrecision = 'year' WHERE EXTRACT(MONTH FROM openingDate) = 1 AND EXTRACT(DAY FROM openingDate) = 1");
        $this->addSql("UPDATE coaster SET closingDatePrecision = 'year' WHERE EXTRACT(MONTH FROM closingDate) = 1 AND EXTRACT(DAY FROM closingDate) = 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coaster DROP openingDatePrecision, DROP closingDatePrecision');
    }
}
