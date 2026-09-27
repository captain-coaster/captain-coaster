<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Staged ranking (#453): one ranking per month, published_at, run report; duel details in ranking_history.';
    }

    public function up(Schema $schema): void
    {
        // A month computed twice (February 2025): keep the run with history
        $this->addSql('DELETE r FROM ranking r
            JOIN ranking o ON o.id > r.id AND DATE_FORMAT(o.computed_at, \'%Y-%m\') = DATE_FORMAT(r.computed_at, \'%Y-%m\')
            WHERE NOT EXISTS (SELECT 1 FROM ranking_history h WHERE h.ranking_id = r.id)');

        $this->addSql('ALTER TABLE ranking ADD month DATE DEFAULT NULL, ADD published_at DATETIME DEFAULT NULL, ADD report JSON DEFAULT NULL');
        $this->addSql('UPDATE ranking SET month = DATE_FORMAT(computed_at, \'%Y-%m-01\'), published_at = computed_at');
        $this->addSql('ALTER TABLE ranking MODIFY month DATE NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_80B839D08EB61006 ON ranking (month)');

        $this->addSql('ALTER TABLE ranking_history DROP FOREIGN KEY `FK_2F6B262120F64684`');
        $this->addSql('ALTER TABLE ranking_history ADD won INT DEFAULT 0 NOT NULL, ADD lost INT DEFAULT 0 NOT NULL, ADD tied INT DEFAULT 0 NOT NULL, ADD riders INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE ranking_history ADD CONSTRAINT FK_2F6B262120F64684 FOREIGN KEY (ranking_id) REFERENCES ranking (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ranking_history DROP FOREIGN KEY `FK_2F6B262120F64684`');
        $this->addSql('ALTER TABLE ranking_history DROP won, DROP lost, DROP tied, DROP riders');
        $this->addSql('ALTER TABLE ranking_history ADD CONSTRAINT FK_2F6B262120F64684 FOREIGN KEY (ranking_id) REFERENCES ranking (id)');
        $this->addSql('DROP INDEX UNIQ_80B839D08EB61006 ON ranking');
        $this->addSql('ALTER TABLE ranking DROP month, DROP published_at, DROP report');
    }
}
