<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250615210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add granular cookie selection flag to cookie consent config profiles.';
    }

    public function up(Schema $schema): void
    {
        $configTable = 'demo_nowo_cookie_consent_config';

        if (!$this->columnExists($configTable, 'granular_cookie_selection')) {
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN granular_cookie_selection BOOLEAN DEFAULT 0 NOT NULL");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $columns = $this->connection->fetchAllAssociative(sprintf('PRAGMA table_info(%s)', $table));

        foreach ($columns as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    public function down(Schema $schema): void
    {
    }
}
