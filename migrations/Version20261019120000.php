<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI calls ledger: one row per Bedrock call with its tokens and cost';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_call (id INT AUTO_INCREMENT NOT NULL, task VARCHAR(32) NOT NULL, model VARCHAR(32) NOT NULL, subject_id INT DEFAULT NULL, language VARCHAR(2) DEFAULT NULL, success TINYINT NOT NULL, error_code VARCHAR(64) DEFAULT NULL, input_tokens INT NOT NULL, output_tokens INT NOT NULL, cache_read_tokens INT NOT NULL, cache_write_tokens INT NOT NULL, cost_usd NUMERIC(10, 6) NOT NULL, latency_ms INT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_ai_call_task_created_at (task, created_at), INDEX idx_ai_call_task_subject (task, subject_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_call');
    }
}
