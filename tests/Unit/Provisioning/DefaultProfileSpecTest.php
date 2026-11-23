<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Provisioning;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Provisioning\DefaultProfileSpec;
use Nowo\CookieConsentBundle\Provisioning\InvalidProvisioningSpecException;
use Nowo\CookieConsentBundle\Provisioning\ProfileTranslationSpec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function method_exists;
use function str_repeat;
use function ucfirst;

final class DefaultProfileSpecTest extends TestCase
{
    /**
     * Every setting is a setting of the entity (setter and getter), so the provisioner can write and compare it.
     */
    public function testEverySettingMirrorsAnEntitySetterAndGetter(): void
    {
        $config = new CookieConsentConfig();
        foreach (DefaultProfileSpec::SETTINGS as $setting => $kind) {
            self::assertTrue(method_exists($config, 'set' . ucfirst($setting)), $setting);
            self::assertTrue(method_exists($config, ($kind === 'bool' ? 'is' : 'get') . ucfirst($setting)), $setting);
            self::assertTrue(method_exists(DefaultProfileSpec::class, 'with' . ucfirst($setting)), $setting);
        }
    }

    public function testAnEmptySpecificationManagesNothing(): void
    {
        $spec = DefaultProfileSpec::create();

        self::assertSame([], $spec->settings());
        self::assertSame([], $spec->translations());
    }

    public function testWithersAreImmutableAndKeepTheGivenValues(): void
    {
        $empty = DefaultProfileSpec::create();
        $spec  = $empty->withName('Demo')->withPriority(3)->withRoutePatterns('a', 'b')->withAutoShowRoutes('home');

        self::assertSame([], $empty->settings());
        self::assertSame(['name' => 'Demo', 'priority' => 3, 'routePatterns' => ['a', 'b'], 'autoShowRoutes' => ['home']], $spec->settings());
    }

    public function testEveryWitherStoresItsSetting(): void
    {
        $spec = $this->full();

        self::assertSame(array_keys(DefaultProfileSpec::SETTINGS), array_keys($spec->settings()));
        self::assertSame(['en'], [$spec->translations()[0]->locale]);
    }

    public function testFromArrayMatchesTheWithers(): void
    {
        $data = $this->fullArray();

        self::assertEquals($this->full(), DefaultProfileSpec::fromArray($data));
    }

    public function testFromArrayAcceptsNullsForNullableSettings(): void
    {
        $spec = DefaultProfileSpec::fromArray(['name' => null, 'consentModalPositionX' => null, 'preferencesBubbleIcon' => null, 'preferencesBubbleBorderColor' => '']);

        self::assertSame(['name' => null, 'consentModalPositionX' => null, 'preferencesBubbleBorderColor' => '', 'preferencesBubbleIcon' => null], $spec->settings());
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('invalidArrays')]
    public function testFromArrayRefusesInvalidData(array $data, string $message): void
    {
        $this->expectException(InvalidProvisioningSpecException::class);
        $this->expectExceptionMessage($message);

        DefaultProfileSpec::fromArray($data);
    }

    /**
     * @return iterable<string, array{0: array<mixed>, 1: string}>
     */
    public static function invalidArrays(): iterable
    {
        yield 'unknown key' => [['colour' => 'dark'], 'Unknown key "colour"'];
        yield 'list instead of map' => [['dark'], 'string keys'];
        yield 'bool coerced' => [['autoShow' => 1], 'must be a boolean'];
        yield 'int coerced' => [['priority' => '3'], 'must be an integer'];
        yield 'string expected' => [['colorTheme' => true], 'must be a string'];
        yield 'list expected' => [['routePatterns' => 'a'], 'must be a list of strings'];
        yield 'map instead of list' => [['routePatterns' => ['x' => 'a']], 'must be a list of strings'];
        yield 'non-string in list' => [['autoShowRoutes' => [1]], 'must be a string'];
        yield 'translations not a map' => [['translations' => 'en'], 'must be an object'];
        yield 'translation not a map' => [['translations' => ['en' => 'x']], 'must be an object'];
    }

    /**
     * @param callable(DefaultProfileSpec): DefaultProfileSpec $build
     */
    #[DataProvider('invalidSettings')]
    public function testInvalidSettingsAreRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidProvisioningSpecException::class);
        $this->expectExceptionMessage($message);

        $build(DefaultProfileSpec::create());
    }

    /**
     * @return iterable<string, array{0: callable(DefaultProfileSpec): DefaultProfileSpec, 1: string}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'name too long' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withName(str_repeat('a', 101)), 'longer than 100'];
        yield 'route too long' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withRoutePatterns(str_repeat('a', 256)), 'longer than 255'];
        yield 'auto show route too long' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withAutoShowRoutes(str_repeat('a', 256)), 'longer than 255'];
        yield 'route mode' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withAutoShowRouteMode('some'), 'must be one of'];
        yield 'layout' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withConsentModalLayout('popup'), 'must be one of'];
        yield 'preferences layout' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesModalLayout('popup'), 'must be one of'];
        yield 'variant' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withConsentModalVariant('narrow'), 'must be one of'];
        yield 'preferences variant' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesModalVariant('narrow'), 'must be one of'];
        yield 'position y' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withConsentModalPositionY('up'), 'must be one of'];
        yield 'preferences position y' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesModalPositionY('up'), 'must be one of'];
        yield 'position x' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withConsentModalPositionX('east'), 'must be one of'];
        yield 'preferences position x' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesModalPositionX('east'), 'must be one of'];
        yield 'color theme' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withColorTheme('pink'), 'must be one of'];
        yield 'bubble position' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesBubblePosition('middle'), 'must be one of'];
        yield 'border color' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesBubbleBorderColor('red'), 'hex color'];
        yield 'bar has no wide variant' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withConsentModalLayout('bar')->withConsentModalVariant('wide'), 'not available with the "bar" layout'];
        yield 'bar has no top position (layout last)' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPreferencesModalPositionY('top')->withPreferencesModalLayout('bar'), 'not available with the "bar" layout'];
        yield 'bar has no horizontal position' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withConsentModalLayout('bar')->withConsentModalPositionX('left'), 'not available with the "bar" layout'];
        yield 'priority above 32 bits' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withPriority(2147483648), 'must be between'];
        yield 'locale twice' => [static fn (DefaultProfileSpec $s): DefaultProfileSpec => $s->withTranslation(self::translation())->withTranslation(self::translation()), 'given twice'];
    }

    public function testManagesModalLayout(): void
    {
        $spec = DefaultProfileSpec::create()->withPreferencesModalPositionX(null)->withColorTheme('dark');

        self::assertTrue($spec->managesModalLayout('preferencesModal'));
        self::assertFalse($spec->managesModalLayout('consentModal'));
        self::assertSame(2147483647, DefaultProfileSpec::create()->withPriority(2147483647)->settings()['priority']);
    }

    public function testAValidLayoutCombinationIsAccepted(): void
    {
        $spec = DefaultProfileSpec::create()->withConsentModalLayout('bar')->withConsentModalPositionX(null)->withConsentModalPositionY('bottom')->withConsentModalVariant('inline');

        self::assertSame('bar', $spec->settings()['consentModalLayout']);
    }

    private static function translation(): ProfileTranslationSpec
    {
        return new ProfileTranslationSpec('en', 'Title', 'Text', 'All', 'Necessary');
    }

    private function full(): DefaultProfileSpec
    {
        return DefaultProfileSpec::create()
            ->withName('Demo')
            ->withPriority(5)
            ->withRoutePatterns('app_*')
            ->withAutoShow(false)
            ->withAutoShowRouteMode('except')
            ->withAutoShowRoutes('app_login')
            ->withManageScriptTags(true)
            ->withAutoClearCookies(true)
            ->withHideFromBots(false)
            ->withDisablePageInteraction(true)
            ->withLazyHtmlGeneration(true)
            ->withConsentModalLayout('cloud')
            ->withConsentModalVariant('inline')
            ->withConsentModalPositionY('middle')
            ->withConsentModalPositionX('center')
            ->withConsentModalEqualWeightButtons(false)
            ->withConsentModalFlipButtons(true)
            ->withPreferencesModalLayout('box')
            ->withPreferencesModalVariant('wide')
            ->withPreferencesModalPositionY('top')
            ->withPreferencesModalPositionX('right')
            ->withPreferencesModalEqualWeightButtons(false)
            ->withPreferencesModalFlipButtons(true)
            ->withColorTheme('light-funky')
            ->withDarkModeEnabled(true)
            ->withDisableTransitions(true)
            ->withTwoStepModal(true)
            ->withOpenPreferencesModal(true)
            ->withManageIframePlaceholders(true)
            ->withGranularCookieSelection(true)
            ->withPreferencesBubbleEnabled(true)
            ->withPreferencesBubblePosition('top-left')
            ->withPreferencesBubbleBorderColor('#abc')
            ->withPreferencesBubbleIcon('<svg></svg>')
            ->withTranslation(self::translation());
    }

    /**
     * @return array<string, mixed>
     */
    private function fullArray(): array
    {
        return [
            'name'                               => 'Demo',
            'priority'                           => 5,
            'routePatterns'                      => ['app_*'],
            'autoShow'                           => false,
            'autoShowRouteMode'                  => 'except',
            'autoShowRoutes'                     => ['app_login'],
            'manageScriptTags'                   => true,
            'autoClearCookies'                   => true,
            'hideFromBots'                       => false,
            'disablePageInteraction'             => true,
            'lazyHtmlGeneration'                 => true,
            'consentModalLayout'                 => 'cloud',
            'consentModalVariant'                => 'inline',
            'consentModalPositionY'              => 'middle',
            'consentModalPositionX'              => 'center',
            'consentModalEqualWeightButtons'     => false,
            'consentModalFlipButtons'            => true,
            'preferencesModalLayout'             => 'box',
            'preferencesModalVariant'            => 'wide',
            'preferencesModalPositionY'          => 'top',
            'preferencesModalPositionX'          => 'right',
            'preferencesModalEqualWeightButtons' => false,
            'preferencesModalFlipButtons'        => true,
            'colorTheme'                         => 'light-funky',
            'darkModeEnabled'                    => true,
            'disableTransitions'                 => true,
            'twoStepModal'                       => true,
            'openPreferencesModal'               => true,
            'manageIframePlaceholders'           => true,
            'granularCookieSelection'            => true,
            'preferencesBubbleEnabled'           => true,
            'preferencesBubblePosition'          => 'top-left',
            'preferencesBubbleBorderColor'       => '#abc',
            'preferencesBubbleIcon'              => '<svg></svg>',
            'translations'                       => ['en' => [
                'consentModalTitle'              => 'Title',
                'consentModalDescription'        => 'Text',
                'consentModalAcceptAllBtn'       => 'All',
                'consentModalAcceptNecessaryBtn' => 'Necessary',
            ]],
        ];
    }
}
