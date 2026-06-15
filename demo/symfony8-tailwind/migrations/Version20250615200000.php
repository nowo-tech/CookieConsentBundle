<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250615200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CookieConsent v3 UX columns, preference sections and cookie definition inventory tables.';
    }

    public function up(Schema $schema): void
    {
        $configTable = 'demo_tailwind_nowo_cookie_consent_config';
        $translationTable = 'demo_tailwind_nowo_cookie_consent_config_translation';

        if (!$this->columnExists($configTable, 'color_theme')) {
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN color_theme VARCHAR(30) DEFAULT 'light' NOT NULL");
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN dark_mode_enabled BOOLEAN DEFAULT 0 NOT NULL");
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN disable_transitions BOOLEAN DEFAULT 0 NOT NULL");
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN two_step_modal BOOLEAN DEFAULT 0 NOT NULL");
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN open_preferences_modal BOOLEAN DEFAULT 0 NOT NULL");
            $this->addSql("ALTER TABLE {$configTable} ADD COLUMN manage_iframe_placeholders BOOLEAN DEFAULT 0 NOT NULL");
        }

        if (!$this->columnExists($translationTable, 'preference_sections')) {
            $this->addSql("ALTER TABLE {$translationTable} ADD COLUMN preference_sections CLOB DEFAULT NULL");
        }

        if (!$this->tableExists('demo_tailwind_nowo_cookie_consent_cookie_definition')) {
            $this->addSql('CREATE TABLE demo_tailwind_nowo_cookie_consent_cookie_definition (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, config_id INTEGER NOT NULL, name VARCHAR(120) NOT NULL, duration VARCHAR(60) NOT NULL, category VARCHAR(40) NOT NULL, type VARCHAR(20) NOT NULL, sort_order INTEGER DEFAULT 0 NOT NULL, CONSTRAINT FK_DEMO_TAILWIND_COOKIE_DEFINITION_CONFIG FOREIGN KEY (config_id) REFERENCES demo_tailwind_nowo_cookie_consent_config (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_cookie_definition_config_name ON demo_tailwind_nowo_cookie_consent_cookie_definition (config_id, name)');
            $this->addSql('CREATE TABLE demo_tailwind_nowo_cookie_consent_cookie_definition_translation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, definition_id INTEGER NOT NULL, locale VARCHAR(10) NOT NULL, provider VARCHAR(120) NOT NULL, purpose CLOB NOT NULL, CONSTRAINT FK_DEMO_TAILWIND_COOKIE_DEFINITION_TRANSLATION FOREIGN KEY (definition_id) REFERENCES demo_tailwind_nowo_cookie_consent_cookie_definition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_cookie_definition_translation_locale ON demo_tailwind_nowo_cookie_consent_cookie_definition_translation (definition_id, locale)');
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->connection->fetchOne(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table],
        ) !== false;
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
        $this->addSql('DROP TABLE demo_tailwind_nowo_cookie_consent_cookie_definition_translation');
        $this->addSql('DROP TABLE demo_tailwind_nowo_cookie_consent_cookie_definition');
        $this->addSql('CREATE TABLE __temp_demo_tailwind_nowo_cookie_consent_config_translation AS SELECT id, config_id, locale, consent_modal_label, consent_modal_title, consent_modal_description, consent_modal_accept_all_btn, consent_modal_accept_necessary_btn, consent_modal_show_preferences_btn, consent_modal_footer, preferences_modal_title, preferences_modal_accept_all_btn, preferences_modal_accept_necessary_btn, preferences_modal_save_preferences_btn, preferences_modal_close_icon_label, privacy_route FROM demo_tailwind_nowo_cookie_consent_config_translation');
        $this->addSql('DROP TABLE demo_tailwind_nowo_cookie_consent_config_translation');
        $this->addSql('CREATE TABLE demo_tailwind_nowo_cookie_consent_config_translation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, config_id INTEGER NOT NULL, locale VARCHAR(10) NOT NULL, consent_modal_label VARCHAR(100) DEFAULT NULL, consent_modal_title VARCHAR(100) NOT NULL, consent_modal_description CLOB NOT NULL, consent_modal_accept_all_btn VARCHAR(30) NOT NULL, consent_modal_accept_necessary_btn VARCHAR(30) NOT NULL, consent_modal_show_preferences_btn VARCHAR(30) DEFAULT NULL, consent_modal_footer CLOB DEFAULT NULL, preferences_modal_title VARCHAR(100) DEFAULT NULL, preferences_modal_accept_all_btn VARCHAR(30) DEFAULT NULL, preferences_modal_accept_necessary_btn VARCHAR(30) DEFAULT NULL, preferences_modal_save_preferences_btn VARCHAR(30) DEFAULT NULL, preferences_modal_close_icon_label VARCHAR(30) DEFAULT NULL, privacy_route VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_COOKIE_CONSENT_CONFIG_TRANSLATION_CONFIG FOREIGN KEY (config_id) REFERENCES demo_tailwind_nowo_cookie_consent_config (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO demo_tailwind_nowo_cookie_consent_config_translation (id, config_id, locale, consent_modal_label, consent_modal_title, consent_modal_description, consent_modal_accept_all_btn, consent_modal_accept_necessary_btn, consent_modal_show_preferences_btn, consent_modal_footer, preferences_modal_title, preferences_modal_accept_all_btn, preferences_modal_accept_necessary_btn, preferences_modal_save_preferences_btn, preferences_modal_close_icon_label, privacy_route) SELECT id, config_id, locale, consent_modal_label, consent_modal_title, consent_modal_description, consent_modal_accept_all_btn, consent_modal_accept_necessary_btn, consent_modal_show_preferences_btn, consent_modal_footer, preferences_modal_title, preferences_modal_accept_all_btn, preferences_modal_accept_necessary_btn, preferences_modal_save_preferences_btn, preferences_modal_close_icon_label, privacy_route FROM __temp_demo_tailwind_nowo_cookie_consent_config_translation');
        $this->addSql('DROP TABLE __temp_demo_tailwind_nowo_cookie_consent_config_translation');
        $this->addSql('CREATE UNIQUE INDEX uniq_cookie_consent_config_translation_locale ON demo_tailwind_nowo_cookie_consent_config_translation (config_id, locale)');
        $this->addSql('CREATE TABLE __temp_demo_tailwind_nowo_cookie_consent_config AS SELECT id, enabled, is_default, auto_show, revision, manage_script_tags, auto_clear_cookies, hide_from_bots, disable_page_interaction, lazy_html_generation, consent_modal_layout, consent_modal_variant, consent_modal_position_y, consent_modal_position_x, consent_modal_equal_weight_buttons, consent_modal_flip_buttons, preferences_modal_layout, preferences_modal_variant, preferences_modal_position_y, preferences_modal_position_x, preferences_modal_equal_weight_buttons, preferences_modal_flip_buttons, name, route_patterns, priority, auto_show_route_mode, auto_show_routes FROM demo_tailwind_nowo_cookie_consent_config');
        $this->addSql('DROP TABLE demo_tailwind_nowo_cookie_consent_config');
        $this->addSql('CREATE TABLE demo_tailwind_nowo_cookie_consent_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, enabled BOOLEAN DEFAULT 1 NOT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, auto_show BOOLEAN DEFAULT 1 NOT NULL, revision INTEGER DEFAULT 0 NOT NULL, manage_script_tags BOOLEAN DEFAULT 0 NOT NULL, auto_clear_cookies BOOLEAN DEFAULT 0 NOT NULL, hide_from_bots BOOLEAN DEFAULT 1 NOT NULL, disable_page_interaction BOOLEAN DEFAULT 0 NOT NULL, lazy_html_generation BOOLEAN DEFAULT 0 NOT NULL, consent_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, consent_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, consent_modal_position_y VARCHAR(20) DEFAULT \'bottom\' NOT NULL, consent_modal_position_x VARCHAR(20) DEFAULT \'center\', consent_modal_equal_weight_buttons BOOLEAN DEFAULT 0 NOT NULL, consent_modal_flip_buttons BOOLEAN DEFAULT 0 NOT NULL, preferences_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, preferences_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, preferences_modal_position_y VARCHAR(20) DEFAULT \'middle\' NOT NULL, preferences_modal_position_x VARCHAR(20) DEFAULT \'center\', preferences_modal_equal_weight_buttons BOOLEAN DEFAULT 0 NOT NULL, preferences_modal_flip_buttons BOOLEAN DEFAULT 0 NOT NULL, name VARCHAR(100) DEFAULT NULL, route_patterns CLOB NOT NULL DEFAULT \'[]\', priority INTEGER DEFAULT 0 NOT NULL, auto_show_route_mode VARCHAR(20) DEFAULT \'all\' NOT NULL, auto_show_routes CLOB NOT NULL DEFAULT \'[]\')');
        $this->addSql('INSERT INTO demo_tailwind_nowo_cookie_consent_config (id, enabled, is_default, auto_show, revision, manage_script_tags, auto_clear_cookies, hide_from_bots, disable_page_interaction, lazy_html_generation, consent_modal_layout, consent_modal_variant, consent_modal_position_y, consent_modal_position_x, consent_modal_equal_weight_buttons, consent_modal_flip_buttons, preferences_modal_layout, preferences_modal_variant, preferences_modal_position_y, preferences_modal_position_x, preferences_modal_equal_weight_buttons, preferences_modal_flip_buttons, name, route_patterns, priority, auto_show_route_mode, auto_show_routes) SELECT id, enabled, is_default, auto_show, revision, manage_script_tags, auto_clear_cookies, hide_from_bots, disable_page_interaction, lazy_html_generation, consent_modal_layout, consent_modal_variant, consent_modal_position_y, consent_modal_position_x, consent_modal_equal_weight_buttons, consent_modal_flip_buttons, preferences_modal_layout, preferences_modal_variant, preferences_modal_position_y, preferences_modal_position_x, preferences_modal_equal_weight_buttons, preferences_modal_flip_buttons, name, route_patterns, priority, auto_show_route_mode, auto_show_routes FROM __temp_demo_tailwind_nowo_cookie_consent_config');
        $this->addSql('DROP TABLE __temp_demo_tailwind_nowo_cookie_consent_config');
    }
}
