<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019075838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Convert serialized PHP arrays to JSON format using PHP
        $connection = $this->connection;
        $users = $connection->fetchAllAssociative('SELECT id, roles FROM users WHERE roles IS NOT NULL');

        foreach ($users as $user) {
            $roles = $user['roles'];
            if (empty($roles)) {
                $jsonRoles = '[]';
            } else {
                try {
                    $unserializedRoles = unserialize($roles);
                    $jsonRoles = json_encode($unserializedRoles ?: []);
                } catch (\Exception $e) {
                    $jsonRoles = '[]';
                }
            }

            $connection->executeStatement(
                'UPDATE users SET roles = ? WHERE id = ?',
                [$jsonRoles, $user['id']]
            );
        }

        $this->addSql('ALTER TABLE users CHANGE roles roles JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Convert JSON back to serialized PHP arrays using PHP
        $connection = $this->connection;
        $users = $connection->fetchAllAssociative('SELECT id, roles FROM users WHERE roles IS NOT NULL');

        foreach ($users as $user) {
            $roles = $user['roles'];
            if (empty($roles)) {
                $serializedRoles = 'a:0:{}';
            } else {
                try {
                    $jsonRoles = json_decode($roles, true);
                    $serializedRoles = serialize($jsonRoles ?: []);
                } catch (\Exception $e) {
                    $serializedRoles = 'a:0:{}';
                }
            }

            $connection->executeStatement(
                'UPDATE users SET roles = ? WHERE id = ?',
                [$serializedRoles, $user['id']]
            );
        }

        $this->addSql('ALTER TABLE users CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE roles roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\'');
    }
}
