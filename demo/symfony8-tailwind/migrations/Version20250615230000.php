<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250615230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allowed_by_default flag to cookie definition inventory rows.';
    }

    public function up(Schema $schema): void
    {
        $table = 'demo_tailwind_nowo_cookie_consent_cookie_definition';

        if (!$this->columnExists($table, 'allowed_by_default')) {
            $this->addSql("ALTER TABLE {$table} ADD COLUMN allowed_by_default BOOLEAN DEFAULT 1 NOT NULL");
        }

        $this->addSql("UPDATE {$table} SET allowed_by_default = 0 WHERE category IN ('analytics', 'marketing')");
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
