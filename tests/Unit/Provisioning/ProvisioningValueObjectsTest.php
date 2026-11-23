<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Provisioning;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisioningException;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpec;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionSpecs;
use Nowo\CookieConsentBundle\Provisioning\CookieDefinitionTranslationSpec;
use Nowo\CookieConsentBundle\Provisioning\InvalidProvisioningSpecException;
use Nowo\CookieConsentBundle\Provisioning\ProfileTranslationSpec;
use Nowo\CookieConsentBundle\Provisioning\ProvisioningResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function array_values;
use function iterator_to_array;
use function method_exists;
use function str_repeat;
use function ucfirst;

final class ProvisioningValueObjectsTest extends TestCase
{
    public function testProfileTranslationTextsMirrorTheEntity(): void
    {
        $translation = new ProfileTranslationSpec('pt_BR', 'T', 'D', 'A', 'N', 'L', 'P', 'F', 'PT', 'PA', 'PN', 'PS', 'PC', 'privacy');

        self::assertSame(array_keys(ProfileTranslationSpec::FIELDS), array_keys($translation->texts()));
        self::assertSame(['T', 'D', 'A', 'N', 'L', 'P', 'F', 'PT', 'PA', 'PN', 'PS', 'PC', 'privacy'], array_values($translation->texts()));
        foreach (array_keys(ProfileTranslationSpec::FIELDS) as $field) {
            self::assertTrue(method_exists(CookieConsentConfigTranslation::class, 'set' . ucfirst($field)), $field);
            self::assertTrue(method_exists(CookieConsentConfigTranslation::class, 'get' . ucfirst($field)), $field);
        }
    }

    public function testProfileTranslationFromArray(): void
    {
        self::assertSame('app_privacy.v2-legal', (new ProfileTranslationSpec('en', 'T', 'D', 'A', 'N', privacyRoute: 'app_privacy.v2-legal'))->privacyRoute, 'a route name');

        $translation = ProfileTranslationSpec::fromArray('es', [
            'consentModalTitle'              => 'Cookies',
            'consentModalDescription'        => 'Texto',
            'consentModalAcceptAllBtn'       => 'Todo',
            'consentModalAcceptNecessaryBtn' => 'Necesarias',
            'privacyRoute'                   => 'app_privacy',
            'consentModalFooter'             => null,
        ]);

        self::assertEquals(new ProfileTranslationSpec('es', 'Cookies', 'Texto', 'Todo', 'Necesarias', privacyRoute: 'app_privacy'), $translation);
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalid')]
    public function testInvalidValuesAreRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidProvisioningSpecException::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    /**
     * @return iterable<string, array{0: callable(): mixed, 1: string}>
     */
    public static function invalid(): iterable
    {
        yield 'locale' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('english', 'T', 'D', 'A', 'N'), 'locale code'];
        yield 'locale too long' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('en-abcdefgh', 'T', 'D', 'A', 'N'), 'locale code'];
        yield 'locale with space' => [static fn (): CookieDefinitionTranslationSpec => new CookieDefinitionTranslationSpec('en ', 'P'), 'locale code'];
        yield 'title too long' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('en', str_repeat('é', 101), 'D', 'A', 'N'), 'longer than 100 characters'];
        yield 'button too long' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('en', 'T', 'D', 'A', 'N', consentModalShowPreferencesBtn: str_repeat('a', 31)), 'longer than 30 characters'];
        yield 'text too long' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('en', 'T', str_repeat('a', 65536), 'A', 'N'), 'longer than 65535 bytes'];
        yield 'profile translation missing key' => [static fn (): ProfileTranslationSpec => ProfileTranslationSpec::fromArray('en', ['consentModalTitle' => 'T']), 'Missing key "consentModalDescription"'];
        yield 'profile translation unknown key' => [static fn (): ProfileTranslationSpec => ProfileTranslationSpec::fromArray('en', ['title' => 'T']), 'Unknown key "title"'];
        yield 'profile translation wrong type' => [static fn (): ProfileTranslationSpec => ProfileTranslationSpec::fromArray('en', ['consentModalTitle' => 1, 'consentModalDescription' => 'D', 'consentModalAcceptAllBtn' => 'A', 'consentModalAcceptNecessaryBtn' => 'N']), 'must be a string'];
        yield 'empty name' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec(''), 'non-empty string'];
        yield 'name with trailing space' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('nid '), 'leading or trailing whitespace'];
        yield 'name with control character' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec("n\x01id"), 'control characters'];
        yield 'name invalid UTF-8' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec("n\xffid"), 'control characters'];
        yield 'name too long' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec(str_repeat('a', 121)), 'longer than 120'];
        yield 'category blank' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('_ga', ' '), 'category must be a non-empty string'];
        yield 'category too long' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('_ga', str_repeat('a', 41)), 'longer than 40'];
        yield 'duration too long' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('_ga', 'analytics', str_repeat('a', 61)), 'longer than 60'];
        yield 'type' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('_ga', 'analytics', '', 'fourth_party'), 'must be one of'];
        yield 'provider too long' => [static fn (): CookieDefinitionTranslationSpec => new CookieDefinitionTranslationSpec('en', str_repeat('a', 121)), 'longer than 120'];
        yield 'definition translation twice' => [static fn (): CookieDefinitionSpec => (new CookieDefinitionSpec('_ga'))->withTranslation(new CookieDefinitionTranslationSpec('en'))->withTranslation(new CookieDefinitionTranslationSpec('en')), 'given twice'];
        yield 'definition twice' => [static fn (): CookieDefinitionSpecs => new CookieDefinitionSpecs(new CookieDefinitionSpec('_ga'), new CookieDefinitionSpec('_ga')), 'given twice'];
        yield 'definitions not a list' => [static fn (): CookieDefinitionSpecs => CookieDefinitionSpecs::fromArray(['a' => ['name' => '_ga']]), 'must be a list'];
        yield 'definition not a map' => [static fn (): CookieDefinitionSpecs => CookieDefinitionSpecs::fromArray(['_ga']), 'must be an object, got string'];
        yield 'definition missing name' => [static fn (): CookieDefinitionSpec => CookieDefinitionSpec::fromArray(['category' => 'analytics']), 'Missing key "name"'];
        yield 'definition unknown key' => [static fn (): CookieDefinitionSpec => CookieDefinitionSpec::fromArray(['name' => '_ga', 'provider' => 'Google']), 'Unknown key "provider"'];
        yield 'definition sort order coerced' => [static fn (): CookieDefinitionSpec => CookieDefinitionSpec::fromArray(['name' => '_ga', 'sort_order' => '1']), 'must be an integer'];
        yield 'definition allowed_by_default coerced' => [static fn (): CookieDefinitionSpec => CookieDefinitionSpec::fromArray(['name' => '_ga', 'allowed_by_default' => 'yes']), 'must be a boolean'];
        yield 'definition translation unknown key' => [static fn (): CookieDefinitionTranslationSpec => CookieDefinitionTranslationSpec::fromArray('en', ['provider' => 'G', 'vendor' => 'G']), 'Unknown key "vendor"'];
        yield 'privacy route URL' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('en', 'T', 'D', 'A', 'N', privacyRoute: 'https://example.com/privacy'), 'must be a Symfony route name'];
        yield 'privacy route path' => [static fn (): ProfileTranslationSpec => new ProfileTranslationSpec('en', 'T', 'D', 'A', 'N', privacyRoute: '/privacy'), 'must be a Symfony route name'];
        yield 'privacy route with space' => [static fn (): ProfileTranslationSpec => ProfileTranslationSpec::fromArray('en', ['consentModalTitle' => 'T', 'consentModalDescription' => 'D', 'consentModalAcceptAllBtn' => 'A', 'consentModalAcceptNecessaryBtn' => 'N', 'privacyRoute' => 'app privacy']), 'must be a Symfony route name'];
        yield 'required not pre-ticked' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('session', 'required', allowedByDefault: false), 'always pre-ticked'];
        yield 'required not pre-ticked from array' => [static fn (): CookieDefinitionSpec => CookieDefinitionSpec::fromArray(['name' => 'session', 'category' => 'required', 'allowed_by_default' => false]), 'always pre-ticked'];
        yield 'sort order above 32 bits' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('_ga', sortOrder: 2147483648), 'must be between -2147483648 and 2147483647'];
        yield 'sort order below 32 bits' => [static fn (): CookieDefinitionSpec => new CookieDefinitionSpec('_ga', sortOrder: -2147483649), 'must be between'];
        yield 'definition translations not a map' => [static fn (): CookieDefinitionSpec => CookieDefinitionSpec::fromArray(['name' => '_ga', 'translations' => ['x']]), 'string keys'];
    }

    public function testCookieDefinitionFieldsAreOnlyTheGivenOnes(): void
    {
        $bare = new CookieDefinitionSpec('session');
        $full = new CookieDefinitionSpec('_ga', 'analytics', '2 years', CookieDefinition::TYPE_THIRD_PARTY, 3, false);

        self::assertSame([], $bare->fields(), 'ZAH-3: nothing given, nothing written (the stored values are kept)');
        self::assertNull($bare->category);
        self::assertSame(['category' => 'analytics', 'duration' => '2 years', 'type' => 'third_party', 'sortOrder' => 3, 'allowedByDefault' => false], $full->fields());
        self::assertSame(['allowedByDefault' => true], (new CookieDefinitionSpec('_ga', allowedByDefault: true))->fields());
        self::assertSame([], $full->translations());
        self::assertSame(['category' => 'required', 'allowedByDefault' => true], (new CookieDefinitionSpec('session', 'required', allowedByDefault: true))->fields());
    }

    public function testOptionalTextsAreOnlyWrittenWhenGiven(): void
    {
        $translation = new CookieDefinitionTranslationSpec('en', purpose: 'Statistics');
        self::assertNull($translation->provider);
        self::assertSame('Statistics', $translation->purpose);

        $copy = new ProfileTranslationSpec('en', 'T', 'D', 'A', 'N', consentModalFooter: '');
        self::assertSame(['consentModalTitle' => 'T', 'consentModalDescription' => 'D', 'consentModalAcceptAllBtn' => 'A', 'consentModalAcceptNecessaryBtn' => 'N', 'consentModalFooter' => null], $copy->texts(), 'null keeps, "" clears (stored null, as the admin form: ZAH-12)');
    }

    public function testCookieDefinitionsFromTheCookieInventoryShape(): void
    {
        $specs = CookieDefinitionSpecs::fromArray([
            ['name' => '_ga', 'category' => 'analytics', 'duration' => '2 years', 'type' => 'third_party', 'sort_order' => 2, 'allowed_by_default' => false, 'translations' => [
                'en' => ['provider' => 'Google', 'purpose' => 'Statistics'],
                'es' => ['purpose' => 'Estadísticas'],
            ]],
            ['name' => 'session', 'sortOrder' => 1, 'allowedByDefault' => true],
            ['name' => '123'],
        ]);

        self::assertCount(3, $specs);
        $all = $specs->all();
        self::assertSame($all, iterator_to_array($specs));
        self::assertEquals(
            (new CookieDefinitionSpec('_ga', 'analytics', '2 years', 'third_party', 2, false))
                ->withTranslation(new CookieDefinitionTranslationSpec('en', 'Google', 'Statistics'))
                ->withTranslation(new CookieDefinitionTranslationSpec('es', null, 'Estadísticas')),
            $all[0],
        );
        self::assertSame([1, true, null], [$all[1]->sortOrder, $all[1]->allowedByDefault, $all[1]->category]);
        self::assertSame('123', $all[2]->name, 'a numeric-looking name stays a string key');
        self::assertSame([], $all[2]->fields(), 'ZAH-3: omitted keys keep the stored values');
    }

    public function testResultAndExceptions(): void
    {
        $result = new ProvisioningResult(0, 0, 2, 9);
        self::assertFalse($result->hasChanges());
        self::assertTrue((new ProvisioningResult(0, 1, 0, 9))->hasChanges());
        self::assertTrue((new ProvisioningResult(1, 0, 0, 9))->hasChanges());

        $previous = new RuntimeException('duplicate');
        self::assertSame($previous, CookieConsentProvisioningException::duplicateKey($previous)->getPrevious());
        self::assertStringContainsString('ensureDefaultProfile()', CookieConsentProvisioningException::noDefaultProfile()->getMessage());
    }
}
