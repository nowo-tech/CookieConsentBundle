<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create cookie consent log table for demo (prefixed demo_tailwind_dashboard_cookie_log).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demo_tailwind_dashboard_cookie_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, ip_address VARCHAR(255) NOT NULL, cookie_consent_key VARCHAR(255) NOT NULL, cookie_name VARCHAR(255) NOT NULL, cookie_value BOOLEAN NOT NULL, timestamp DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE demo_tailwind_dashboard_cookie_log');
    }
}
