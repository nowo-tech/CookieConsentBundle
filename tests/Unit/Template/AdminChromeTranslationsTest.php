<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function dirname;

/**
 * Every string the admin pages (UiKit scaffolds, REQ-UI-003) render exists in every shipped locale.
 */
final class AdminChromeTranslationsTest extends TestCase
{
    private const KEYS = [
        'nowo_cookie_consent.admin.area_nav.label',
        'nowo_cookie_consent.admin.area_nav.settings',
        'nowo_cookie_consent.admin.area_nav.cookies',
        'nowo_cookie_consent.admin.config.settings.title',
        'nowo_cookie_consent.admin.config.settings.lead',
        'nowo_cookie_consent.admin.config.settings.section.profile',
        'nowo_cookie_consent.admin.config.settings.section.profile_lead',
        'nowo_cookie_consent.admin.config.settings.section.route_targeting',
        'nowo_cookie_consent.admin.config.settings.section.route_targeting_lead',
        'nowo_cookie_consent.admin.config.settings.save',
        'nowo_cookie_consent.admin.cookie_definition.title',
        'nowo_cookie_consent.admin.cookie_definition.lead',
        'nowo_cookie_consent.admin.cookie_definition.form_lead',
        'nowo_cookie_consent.admin.cookie_definition.new',
        'nowo_cookie_consent.admin.cookie_definition.new_title',
        'nowo_cookie_consent.admin.cookie_definition.edit',
        'nowo_cookie_consent.admin.cookie_definition.edit_title',
        'nowo_cookie_consent.admin.cookie_definition.save',
        'nowo_cookie_consent.admin.cookie_definition.cancel',
        'nowo_cookie_consent.admin.cookie_definition.delete',
        'nowo_cookie_consent.admin.cookie_definition.delete_confirm',
        'nowo_cookie_consent.admin.cookie_definition.empty',
        'nowo_cookie_consent.admin.cookie_definition.table.actions',
        'nowo_cookie_consent.admin.cookie_definition.table.name',
        'nowo_cookie_consent.admin.cookie_definition.table.allowed_by_default',
        'nowo_cookie_consent.admin.cookie_definition.yes',
        'nowo_cookie_consent.admin.cookie_definition.no',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (['ca', 'de', 'en', 'es', 'fr', 'it', 'nl', 'pl', 'pt'] as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testChromeKeysExist(string $locale): void
    {
        $catalog = Yaml::parseFile(dirname(__DIR__, 3) . '/src/Resources/translations/NowoCookieConsentBundle.' . $locale . '.yaml');
        self::assertIsArray($catalog);

        foreach (self::KEYS as $key) {
            self::assertArrayHasKey($key, $catalog, $locale . ' misses ' . $key);
            self::assertNotSame('', $catalog[$key]);
        }
    }
}
