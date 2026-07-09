<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertSame('', $config['table_prefix']);
        self::assertSame('default', $config['doctrine']['connection']);
        self::assertSame('', $config['doctrine']['table_prefix']);
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
        self::assertSame([], $config['cookie_inventory']);
        self::assertFalse($config['preferences_bubble_enabled']);
        self::assertSame('bottom-right', $config['preferences_bubble_position']);
        self::assertNull($config['preferences_bubble_border_color']);
        self::assertNull($config['preferences_bubble_icon']);
        self::assertSame([], $config['preference_sections']);
    }

    public function testDoctrineTablePrefixIsApplied(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'doctrine' => ['table_prefix' => 'app_'],
        ]]);

        self::assertSame('app_', $config['doctrine']['table_prefix']);
    }

    public function testLegacyTablePrefixIsApplied(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[
            'table_prefix' => 'app_',
        ]]);

        self::assertSame('app_', $config['table_prefix']);
    }
}
