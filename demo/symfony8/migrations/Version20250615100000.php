<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Demo\GdprConsentCopy;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250615100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update demo cookie consent modal copy to GDPR-aligned defaults.';
    }

    public function up(Schema $schema): void
    {
        foreach (GdprConsentCopy::defaults() as $row) {
            $this->connection->executeStatement(
                'UPDATE demo_nowo_cookie_consent_config_translation
                 SET consent_modal_title = ?,
                     consent_modal_description = ?,
                     consent_modal_footer = ?,
                     consent_modal_accept_all_btn = ?,
                     consent_modal_accept_necessary_btn = ?,
                     preferences_modal_save_preferences_btn = ?,
                     privacy_route = ?
                 WHERE locale = ?',
                [
                    $row['title'],
                    $row['intro'],
                    $row['readMoreLabel'],
                    $row['acceptAll'],
                    $row['acceptNecessary'],
                    $row['save'],
                    $row['privacyRoute'],
                    $row['locale'],
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
