<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250614130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace demo cookie_consent_config table with bundle configuration entities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS cookie_consent_config');

        $this->addSql('CREATE TABLE demo_tailwind_nowo_cookie_consent_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, enabled BOOLEAN DEFAULT 1 NOT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, auto_show BOOLEAN DEFAULT 1 NOT NULL, revision INTEGER DEFAULT 0 NOT NULL, manage_script_tags BOOLEAN DEFAULT 0 NOT NULL, auto_clear_cookies BOOLEAN DEFAULT 0 NOT NULL, hide_from_bots BOOLEAN DEFAULT 1 NOT NULL, disable_page_interaction BOOLEAN DEFAULT 0 NOT NULL, lazy_html_generation BOOLEAN DEFAULT 0 NOT NULL, consent_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, consent_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, consent_modal_position_y VARCHAR(20) DEFAULT \'bottom\' NOT NULL, consent_modal_position_x VARCHAR(20) DEFAULT \'center\', consent_modal_equal_weight_buttons BOOLEAN DEFAULT 0 NOT NULL, consent_modal_flip_buttons BOOLEAN DEFAULT 0 NOT NULL, preferences_modal_layout VARCHAR(20) DEFAULT \'box\' NOT NULL, preferences_modal_variant VARCHAR(20) DEFAULT \'wide\' NOT NULL, preferences_modal_position_y VARCHAR(20) DEFAULT \'middle\' NOT NULL, preferences_modal_position_x VARCHAR(20) DEFAULT \'center\', preferences_modal_equal_weight_buttons BOOLEAN DEFAULT 0 NOT NULL, preferences_modal_flip_buttons BOOLEAN DEFAULT 0 NOT NULL)');
        $this->addSql('CREATE TABLE demo_tailwind_nowo_cookie_consent_config_translation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, config_id INTEGER NOT NULL, locale VARCHAR(10) NOT NULL, consent_modal_label VARCHAR(100) DEFAULT NULL, consent_modal_title VARCHAR(100) NOT NULL, consent_modal_description CLOB NOT NULL, consent_modal_accept_all_btn VARCHAR(30) NOT NULL, consent_modal_accept_necessary_btn VARCHAR(30) NOT NULL, consent_modal_show_preferences_btn VARCHAR(30) DEFAULT NULL, consent_modal_footer CLOB DEFAULT NULL, preferences_modal_title VARCHAR(100) DEFAULT NULL, preferences_modal_accept_all_btn VARCHAR(30) DEFAULT NULL, preferences_modal_accept_necessary_btn VARCHAR(30) DEFAULT NULL, preferences_modal_save_preferences_btn VARCHAR(30) DEFAULT NULL, preferences_modal_close_icon_label VARCHAR(30) DEFAULT NULL, privacy_route VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_COOKIE_CONSENT_CONFIG_TRANSLATION_CONFIG FOREIGN KEY (config_id) REFERENCES demo_tailwind_nowo_cookie_consent_config (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cookie_consent_config_translation_locale ON demo_tailwind_nowo_cookie_consent_config_translation (config_id, locale)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE demo_tailwind_nowo_cookie_consent_config_translation');
        $this->addSql('DROP TABLE demo_tailwind_nowo_cookie_consent_config');
        $this->addSql('CREATE TABLE cookie_consent_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, locale VARCHAR(10) NOT NULL, title VARCHAR(255) NOT NULL, intro CLOB NOT NULL, read_more_label VARCHAR(255) NOT NULL, privacy_route VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cookie_consent_config_locale ON cookie_consent_config (locale)');
    }
}
