<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_keys;
use function basename;
use function dirname;
use function glob;
use function implode;
use function is_array;
use function iterator_to_array;
use function preg_match;
use function sort;

/**
 * Every shipped locale must define exactly the same translation keys as the English catalogue,
 * so the banner, the preferences modal and the admin never fall back to raw keys or English.
 */
final class TranslationKeyParityTest extends TestCase
{
    private const DIR = '/src/Resources/translations';

    /**
     * @return iterable<string, array{string}>
     */
    public static function catalogues(): iterable
    {
        $files = glob(dirname(__DIR__, 3) . self::DIR . '/NowoCookieConsentBundle.*.yaml');
        self::assertIsArray($files);

        foreach ($files as $file) {
            if (preg_match('/\.([a-zA-Z_]+)\.yaml$/', basename($file), $m) === 1 && $m[1] !== 'en') {
                yield $m[1] => [$m[1]];
            }
        }
    }

    public function testAllExpectedLocalesAreShipped(): void
    {
        $locales = array_keys(iterator_to_array(self::catalogues()));
        sort($locales);

        self::assertSame(['ca', 'de', 'es', 'fr', 'it', 'nl', 'pl', 'pt'], $locales);
    }

    #[DataProvider('catalogues')]
    public function testLocaleHasSameKeysAsEnglish(string $locale): void
    {
        $en     = self::load('en');
        $target = self::load($locale);

        $missing = array_diff(array_keys($en), array_keys($target));
        $extra   = array_diff(array_keys($target), array_keys($en));

        self::assertSame([], $missing, $locale . ' misses keys: ' . implode(', ', $missing));
        self::assertSame([], $extra, $locale . ' has keys unknown to en: ' . implode(', ', $extra));

        foreach ($en as $key => $value) {
            if ($value !== '') {
                self::assertNotSame('', $target[$key], $locale . ' has an empty value for ' . $key);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private static function load(string $locale): array
    {
        $catalog = Yaml::parseFile(dirname(__DIR__, 3) . self::DIR . '/NowoCookieConsentBundle.' . $locale . '.yaml');
        self::assertTrue(is_array($catalog));

        /* @var array<string, string> $catalog */
        return $catalog;
    }
}
