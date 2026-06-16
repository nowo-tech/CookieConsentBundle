<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250616120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preferences bubble custom icon markup to cookie consent config profiles.';
    }

    public function up(Schema $schema): void
    {
        $configTable = 'demo_nowo_cookie_consent_config';

        if (!$this->columnExists($configTable, 'preferences_bubble_icon')) {
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN preferences_bubble_icon CLOB DEFAULT NULL");
        }

        $icon = PreferencesBubbleIconSanitizer::DEMO_EMOJI_ICON_HTML;
        $this->addSql(sprintf(
            "UPDATE %s SET preferences_bubble_icon = %s WHERE preferences_bubble_enabled = 1 AND (preferences_bubble_icon IS NULL OR preferences_bubble_icon = '')",
            $configTable,
            $this->connection->quote($icon),
        ));
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
