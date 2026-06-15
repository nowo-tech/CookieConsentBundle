<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250614120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cookie consent configuration table and seed demo locales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cookie_consent_config (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, locale VARCHAR(10) NOT NULL, title VARCHAR(255) NOT NULL, intro CLOB NOT NULL, read_more_label VARCHAR(255) NOT NULL, privacy_route VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cookie_consent_config_locale ON cookie_consent_config (locale)');

        $this->addSql("INSERT INTO cookie_consent_config (locale, title, intro, read_more_label, privacy_route) VALUES ('en', 'Cookie settings', 'We use cookies to improve your experience.', 'Read our privacy policy', NULL)");
        $this->addSql("INSERT INTO cookie_consent_config (locale, title, intro, read_more_label, privacy_route) VALUES ('es', 'Configuración de cookies', 'Usamos cookies para mejorar tu experiencia.', 'Leer la política de privacidad', NULL)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cookie_consent_config');
    }
}
