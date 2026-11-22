<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_keys;
use function array_values;
use function basename;
use function glob;
use function in_array;
use function is_array;
use function preg_match;
use function preg_replace;
use function sort;
use function sprintf;

/**
 * Guards against untranslated strings: in every non-en catalogue (all domains) a key must exist, and a value identical to
 * the English one (any length, ignoring values without letters once placeholders are removed) must be listed in
 * ALLOWED_IDENTICAL for that locale. In the same way a value identical to the Spanish one (a copy-paste leftover) but different
 * from English must be listed in ALLOWED_IDENTICAL_TO_ES. An allow-list entry that is no longer identical is reported as stale.
 */
final class TranslationCatalogueTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../src/Resources/translations';

    /**
     * Keys that are correctly identical to English in a locale (brands, product and technical names, codes, loanwords
     * and cognates), per locale.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_IDENTICAL = [
        'ca' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.no',
        ],
        'de' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.config.settings.variant.inline',
            'nowo_cookie_consent.admin.cookie_definition.category.marketing',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
        'es' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.category.marketing',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.no',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
        'fr' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.category.marketing',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
        'it' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.category.marketing',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.no',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
        'nl' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.config.settings.variant.inline',
            'nowo_cookie_consent.admin.cookie_definition.category.marketing',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
        'pl' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
        'pt' => [
            'nowo_cookie_consent.admin.config.settings.fields.auto_show_routes_placeholder',
            'nowo_cookie_consent.admin.config.settings.fields.route_patterns_placeholder',
            'nowo_cookie_consent.admin.config.settings.variant.inline',
            'nowo_cookie_consent.admin.cookie_definition.category.marketing',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale_placeholder',
            'nowo_cookie_consent.inventory.name',
            'nowo_cookie_consent.marketing.title',
        ],
    ];

    /**
     * Keys whose value is identical to the Spanish one yet differs from English, and is correct in that locale (true
     * cognates and shared words such as "Perfil", "Barra" or "Cancelar"), per locale. Anything else identical to Spanish
     * is a Spanish leftover that must be translated.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_IDENTICAL_TO_ES = [
        'ca' => [
            'nowo_cookie_consent.analytics.title',
            'nowo_cookie_consent.admin.cookie_definition.edit',
            'nowo_cookie_consent.admin.cookie_definition.delete',
            'nowo_cookie_consent.admin.cookie_definition.yes',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale',
            'nowo_cookie_consent.admin.config.settings.section.profile',
            'nowo_cookie_consent.admin.config.settings.fields.color_theme',
            'nowo_cookie_consent.admin.config.settings.layout.bar',
            'nowo_cookie_consent.admin.config.settings.position_x.none',
        ],
        'it' => [
            'nowo_cookie_consent.admin.cookie_definition.table.type',
            'nowo_cookie_consent.admin.cookie_definition.fields.type',
            'nowo_cookie_consent.admin.config.settings.layout.bar',
            'nowo_cookie_consent.admin.config.settings.position_y.middle',
            'nowo_cookie_consent.admin.config.settings.position_x.center',
        ],
        'pt' => [
            'nowo_cookie_consent.show_preferences',
            'nowo_cookie_consent.intro_less',
            'nowo_cookie_consent.analytics.title',
            'nowo_cookie_consent.inventory.allow',
            'nowo_cookie_consent.admin.cookie_definition.edit',
            'nowo_cookie_consent.admin.cookie_definition.save',
            'nowo_cookie_consent.admin.cookie_definition.cancel',
            'nowo_cookie_consent.admin.cookie_definition.delete',
            'nowo_cookie_consent.admin.cookie_definition.table.type',
            'nowo_cookie_consent.admin.cookie_definition.fields.type',
            'nowo_cookie_consent.admin.cookie_definition.fields.locale',
            'nowo_cookie_consent.admin.config.settings.section.profile',
            'nowo_cookie_consent.admin.config.settings.layout.bar',
            'nowo_cookie_consent.admin.config.settings.position_y.middle',
            'nowo_cookie_consent.admin.config.settings.position_x.center',
            'nowo_cookie_consent.admin.config.settings.color_theme.light',
            'nowo_cookie_consent.admin.config.settings.color_theme.light-funky',
            'nowo_cookie_consent.admin.cookie_definition.edit_title',
        ],
    ];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        $files = glob(self::DIR . '/*.yaml') ?: [];
        sort($files);

        foreach ($files as $file) {
            if (preg_match('/^(.+)\.([a-z]{2})\.yaml$/', basename($file), $m) === 1 && $m[2] !== 'en') {
                yield basename($file) => [$m[1], $m[2]];
            }
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonSpanishCatalogues(): iterable
    {
        foreach (self::catalogues() as $name => [$domain, $locale]) {
            if ($locale !== 'es') {
                yield $name => [$domain, $locale];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testLocaleHasEveryEnglishKey(string $domain, string $locale): void
    {
        $missing = array_values(array_diff(array_keys($this->load($domain, 'en')), array_keys($this->load($domain, $locale))));

        self::assertSame([], $missing, sprintf('Keys of %s.en.yaml missing in %s.%s.yaml', $domain, $domain, $locale));
    }

    #[DataProvider('catalogues')]
    public function testNoUntranslatedEnglishValues(string $domain, string $locale): void
    {
        $english = $this->load($domain, 'en');
        $target  = $this->load($domain, $locale);
        $allowed = self::ALLOWED_IDENTICAL[$locale] ?? [];
        $gaps    = [];

        foreach ($english as $key => $value) {
            if (!isset($target[$key]) || $target[$key] !== $value || in_array($key, $allowed, true)) {
                continue;
            }

            if (preg_match('/[^\W\d_]/u', (string) preg_replace('/%[^%\s]+%|\{[^}]+\}/', '', $value)) === 1) {
                $gaps[] = $key;
            }
        }

        self::assertSame([], $gaps, sprintf('Values identical to English in %s.%s.yaml (translate or allow-list)', $domain, $locale));
    }

    #[DataProvider('nonSpanishCatalogues')]
    public function testNoUntranslatedSpanishValues(string $domain, string $locale): void
    {
        $english = $this->load($domain, 'en');
        $spanish = $this->load($domain, 'es');
        $target  = $this->load($domain, $locale);
        $allowed = self::ALLOWED_IDENTICAL_TO_ES[$locale] ?? [];
        $gaps    = [];

        foreach ($spanish as $key => $value) {
            if (!isset($target[$key]) || $target[$key] !== $value || ($english[$key] ?? null) === $value || in_array($key, $allowed, true)) {
                continue;
            }

            if (preg_match('/[^\W\d_]/u', (string) preg_replace('/%[^%\s]+%|\{[^}]+\}/', '', $value)) === 1) {
                $gaps[] = $key;
            }
        }

        self::assertSame([], $gaps, sprintf('Values identical to Spanish (and different from English) in %s.%s.yaml (translate or allow-list)', $domain, $locale));
    }

    public function testSpanishAllowListHasNoStaleEntries(): void
    {
        $english = [];
        $spanish = [];
        $locales = [];

        foreach (self::catalogues() as [$domain, $locale]) {
            $english += $this->load($domain, 'en');
            $spanish += $this->load($domain, 'es');
            $locales[$locale] = ($locales[$locale] ?? []) + $this->load($domain, $locale);
        }

        $stale = [];

        foreach (self::ALLOWED_IDENTICAL_TO_ES as $locale => $keys) {
            foreach ($keys as $key) {
                $value = $locales[$locale][$key] ?? null;

                if ($value === null || $value !== ($spanish[$key] ?? null) || $value === ($english[$key] ?? null)) {
                    $stale[] = $locale . ':' . $key;
                }
            }
        }

        self::assertSame([], $stale, 'Spanish allow-list entries that no longer exist, are no longer identical to Spanish or now equal English');
    }

    public function testAllowListHasNoStaleEntries(): void
    {
        $english = [];
        $locales = [];

        foreach (self::catalogues() as [$domain, $locale]) {
            $english += $this->load($domain, 'en');
            $locales[$locale] = ($locales[$locale] ?? []) + $this->load($domain, $locale);
        }

        $stale = [];

        foreach (self::ALLOWED_IDENTICAL as $locale => $keys) {
            foreach ($keys as $key) {
                if (!isset($english[$key]) || ($locales[$locale][$key] ?? null) !== $english[$key]) {
                    $stale[] = $locale . ':' . $key;
                }
            }
        }

        self::assertSame([], $stale, 'Allow-list entries that no longer exist or are no longer identical to English');
    }

    /** @return array<string, string> */
    private function load(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(sprintf('%s/%s.%s.yaml', self::DIR, $domain, $locale));

        return $this->flatten(is_array($data) ? $data : []);
    }

    /**
     * @param array<mixed> $tree
     *
     * @return array<string, string>
     */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $out = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $out += $this->flatten($value, $path);
            } else {
                $out[$path] = (string) $value;
            }
        }

        return $out;
    }
}
