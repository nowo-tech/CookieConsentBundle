<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250615220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add floating preferences bubble settings to cookie consent config profiles.';
    }

    public function up(Schema $schema): void
    {
        $configTable = 'demo_nowo_cookie_consent_config';

        if (!$this->columnExists($configTable, 'preferences_bubble_enabled')) {
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN preferences_bubble_enabled BOOLEAN DEFAULT 0 NOT NULL");
        }

        if (!$this->columnExists($configTable, 'preferences_bubble_position')) {
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN preferences_bubble_position VARCHAR(20) DEFAULT 'bottom-right' NOT NULL");
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
