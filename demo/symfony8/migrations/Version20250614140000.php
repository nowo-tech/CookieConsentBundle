<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250614140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile and auto-show route targeting columns to demo cookie consent config.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE demo_dashboard_cookie_config ADD COLUMN auto_show_route_mode VARCHAR(20) DEFAULT 'all' NOT NULL");
        $this->addSql("ALTER TABLE demo_dashboard_cookie_config ADD COLUMN auto_show_routes CLOB NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE demo_dashboard_cookie_config ADD COLUMN name VARCHAR(100) DEFAULT NULL');
        $this->addSql("ALTER TABLE demo_dashboard_cookie_config ADD COLUMN route_patterns CLOB NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE demo_dashboard_cookie_config ADD COLUMN priority INTEGER DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE __temp_demo_dashboard_cookie_config AS SELECT id, enabled, is_default, auto_show, revision, manage_script_tags, auto_clear_cookies, hide_from_bots, disable_page_interaction, lazy_html_generation, consent_modal_layout, consent_modal_variant, consent_modal_position_y, consent_modal_position_x, consent_modal_equal_weight_buttons, consent_modal_flip_buttons, preferences_modal_layout, preferences_modal_variant, preferences_modal_position_y, preferences_modal_position_x, preferences_modal_equal_weight_buttons, preferences_modal_flip_buttons FROM demo_dashboard_cookie_config');
        $this->addSql('DROP TABLE demo_dashboard_cookie_config');
        $this->addSql('CREATE TABLE demo_dashboard_cookie_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, enabled BOOLEAN DEFAULT 1 NOT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, auto_show BOOLEAN DEFAULT 1 NOT NULL, revision INTEGER DEFAULT 0 NOT NULL, manage_script_tags BOOLEAN DEFAULT 0 NOT NULL, auto_clear_cookies BOOLEAN DEFAULT 0 NOT NULL, hide_from_bots BOOLEAN DEFAULT 1 NOT NULL, disable_page_interaction BOOLEAN DEFAULT 0 NOT NULL, lazy_html_generation BOOLEAN DEFAULT 0 NOT NULL, consent_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, consent_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, consent_modal_position_y VARCHAR(20) DEFAULT \'bottom\' NOT NULL, consent_modal_position_x VARCHAR(20) DEFAULT \'center\', consent_modal_equal_weight_buttons BOOLEAN DEFAULT 0 NOT NULL, consent_modal_flip_buttons BOOLEAN DEFAULT 0 NOT NULL, preferences_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, preferences_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, preferences_modal_position_y VARCHAR(20) DEFAULT \'middle\' NOT NULL, preferences_modal_position_x VARCHAR(20) DEFAULT \'center\', preferences_modal_equal_weight_buttons BOOLEAN DEFAULT 0 NOT NULL, preferences_modal_flip_buttons BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('INSERT INTO demo_dashboard_cookie_config (id, enabled, is_default, auto_show, revision, manage_script_tags, auto_clear_cookies, hide_from_bots, disable_page_interaction, lazy_html_generation, consent_modal_layout, consent_modal_variant, consent_modal_position_y, consent_modal_position_x, consent_modal_equal_weight_buttons, consent_modal_flip_buttons, preferences_modal_layout, preferences_modal_variant, preferences_modal_position_y, preferences_modal_position_x, preferences_modal_equal_weight_buttons, preferences_modal_flip_buttons) SELECT id, enabled, is_default, auto_show, revision, manage_script_tags, auto_clear_cookies, hide_from_bots, disable_page_interaction, lazy_html_generation, consent_modal_layout, consent_modal_variant, consent_modal_position_y, consent_modal_position_x, consent_modal_equal_weight_buttons, consent_modal_flip_buttons, preferences_modal_layout, preferences_modal_variant, preferences_modal_position_y, preferences_modal_position_x, preferences_modal_equal_weight_buttons, preferences_modal_flip_buttons FROM __temp_demo_dashboard_cookie_config');
        $this->addSql('DROP TABLE __temp_demo_dashboard_cookie_config');
    }
}
