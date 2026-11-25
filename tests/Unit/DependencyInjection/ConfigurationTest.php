<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertNull($config['privacy_route']);
        self::assertSame('', $config['doctrine']['table_prefix']);
        self::assertNull($config['doctrine']['private_entity_manager']);
        self::assertArrayNotHasKey('table_prefix', $config, 'removed in 2.0');
        self::assertArrayNotHasKey('connection', $config['doctrine'], 'removed in 2.0');
        self::assertArrayNotHasKey('chrome', $config['web_ui'], 'removed in 2.0');
        self::assertSame(
            [
                CategoryEnum::CATEGORY_ANALYTICS,
                CategoryEnum::CATEGORY_MARKETING,
                CategoryEnum::CATEGORY_PREFERENCES,
            ],
            $config['categories'],
        );
        self::assertTrue($config['use_logger']);
        self::assertTrue($config['http_only']);
        self::assertNull($config['form_action']);
        self::assertTrue($config['csrf_protection']);
        self::assertSame('en', $config['default_locale']);
        self::assertSame(['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'], $config['enabled_locales']);
        self::assertTrue($config['detect_locale_from_accept_language']);
        self::assertSame('bootstrap', $config['ui_theme']);
        self::assertSame('light', $config['color_theme']);
        self::assertFalse($config['dark_mode_enabled']);
        self::assertFalse($config['disable_transitions']);
        self::assertFalse($config['disable_page_interaction']);
        self::assertFalse($config['two_step_modal']);
        self::assertFalse($config['open_preferences_modal']);
        self::assertFalse($config['manage_iframe_placeholders']);
        self::assertFalse($config['use_cookie_inventory']);
        self::assertSame([], $config['skip_render_routes']);
        self::assertSame([], $config['render_routes']);
        self::assertSame([], $config['cookie_inventory']);
        self::assertFalse($config['preferences_bubble_enabled']);
        self::assertSame('bottom-right', $config['preferences_bubble_position']);
        self::assertNull($config['preferences_bubble_border_color']);
        self::assertNull($config['preferences_bubble_icon']);
        self::assertSame([], $config['preference_sections']);
        self::assertTrue($config['web_ui']['enabled']);
        self::assertNull($config['web_ui']['layout_template'], 'null = UiKit admin layout (REQ-UI-003)');
        self::assertSame([], $config['web_ui']['templates']);
        self::assertSame(20, $config['web_ui']['list_page_size']);
        self::assertNull($config['security']['access_checker']);
        self::assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        self::assertFalse($config['security']['allow_unauthenticated']);
    }

    public function testPreferencesBubbleIconRejectsUnsafeMarkup(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'preferences_bubble_icon' => '<img/src=x/onerror=alert(1)>',
        ]]);
    }

    public function testPreferencesBubbleIconAcceptsSvg(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'preferences_bubble_icon' => '<svg viewBox="0 0 24 24"><path d="M0 0"/></svg>',
        ]]);

        self::assertSame('<svg viewBox="0 0 24 24"><path d="M0 0"/></svg>', $config['preferences_bubble_icon']);
    }

    public function testDoctrineTablePrefixIsApplied(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'doctrine' => ['table_prefix' => 'app_'],
        ]]);

        self::assertSame('app_', $config['doctrine']['table_prefix']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function removedKeys(): iterable
    {
        yield 'root table_prefix' => [['table_prefix' => 'app_'], 'table_prefix', 'doctrine.table_prefix'];
        yield 'doctrine.connection' => [['doctrine' => ['connection' => 'default']], 'doctrine.connection', 'doctrine.entity_manager'];
        yield 'web_ui.path_prefix' => [['web_ui' => ['path_prefix' => '/admin/cookies']], 'web_ui.path_prefix', '"prefix" of the bundle routes import'];
        yield 'web_ui.css_framework' => [['web_ui' => ['css_framework' => 'tailwind']], 'web_ui.css_framework', 'nowo_ui_kit.css_framework'];
        yield 'web_ui.icon_set' => [['web_ui' => ['icon_set' => 'svg_inline']], 'web_ui.icon_set', 'nowo_ui_kit.icon_set'];
        yield 'web_ui.chrome' => [['web_ui' => ['chrome' => ['row_actions_display' => 'text']]], 'web_ui.chrome', 'nowo_ui_kit.theme.bundles.NowoCookieConsentBundle'];
        yield 'web_ui.chrome (null)' => [['web_ui' => ['chrome' => null]], 'web_ui.chrome', 'nowo_ui_kit.theme.bundles.NowoCookieConsentBundle'];
    }

    /**
     * 2.0: every key removed in 2.0 fails with its replacement and UPGRADING "To 2.0.0", not a generic error.
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('removedKeys')]
    public function testRemovedKeysFailWithTheirReplacement(array $config, string $path, string $replacement): void
    {
        try {
            (new Processor())->processConfiguration(new Configuration(), [$config]);
            self::fail('The removed key ' . $path . ' must fail.');
        } catch (InvalidConfigurationException $e) {
            self::assertStringContainsString('"nowo_cookie_consent.' . $path . '" option was removed in 2.0', $e->getMessage());
            self::assertStringContainsString($replacement, $e->getMessage());
            self::assertStringContainsString('UPGRADING.md "To 2.0.0"', $e->getMessage());
        }
    }

    public function testPrivateEntityManagerFalseFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(Configuration::PRIVATE_ENTITY_MANAGER_FALSE_REMOVED);

        (new Processor())->processConfiguration(new Configuration(), [['doctrine' => ['private_entity_manager' => false]]]);
    }

    public function testPrivateEntityManagerTrueIsStillAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['doctrine' => ['private_entity_manager' => true]]]);

        self::assertTrue($config['doctrine']['private_entity_manager']);
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function layoutTemplates(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty string' => ['', null];
        yield '1.x default (removed CDN layout)' => [Configuration::LEGACY_LAYOUT_TEMPLATE, null];
        yield 'host layout' => ['layouts/admin.html.twig', 'layouts/admin.html.twig'];
    }

    #[DataProvider('layoutTemplates')]
    public function testLayoutTemplateNormalization(mixed $value, ?string $expected): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['web_ui' => ['layout_template' => $value]]]);

        self::assertSame($expected, $config['web_ui']['layout_template']);
    }

    public function testPageTemplatesAcceptKnownPages(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['web_ui' => ['templates' => ['settings' => 'admin/cookie_settings.html.twig']]]]);

        self::assertSame(['settings' => 'admin/cookie_settings.html.twig'], $config['web_ui']['templates']);
    }

    public function testUnknownPageTemplateIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown web_ui.templates page(s): dashboard');

        (new Processor())->processConfiguration(new Configuration(), [['web_ui' => ['templates' => ['dashboard' => 'x.html.twig']]]]);
    }
}
