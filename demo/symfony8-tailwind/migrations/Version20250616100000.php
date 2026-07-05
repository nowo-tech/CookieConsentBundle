<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250616100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preferences bubble border color to cookie consent config profiles.';
    }

    public function up(Schema $schema): void
    {
        $configTable = 'demo_tailwind_dashboard_cookie_config';

        if (!$this->columnExists($configTable, 'preferences_bubble_border_color')) {
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN preferences_bubble_border_color VARCHAR(7) DEFAULT NULL");
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
