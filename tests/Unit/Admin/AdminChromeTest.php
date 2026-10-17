<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Admin;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_keys;
use function dirname;

final class AdminChromeTest extends TestCase
{
    public function testDefaultsKeepOnlyExplicitValues(): void
    {
        $chrome = AdminChrome::resolve();

        self::assertSame([], $chrome['classes']);
        self::assertSame(AdminChrome::DEFAULT_TEMPLATES, $chrome['templates']);
        self::assertSame([], $chrome['attributes']);
        self::assertSame('h1', $chrome['page_title_tag']);
        self::assertNull($chrome['row_actions_display'], 'inherit nowo_ui_kit.row_actions_display by default');
    }

    public function testOverridesAreMerged(): void
    {
        $chrome = AdminChrome::resolve([
            'page_title_tag'      => 'h2',
            'row_actions_display' => 'text',
            'classes'             => ['table' => 'data-table', 'button_primary' => 'btn-primary', 'pagination' => null],
            'attributes'          => ['table_panel' => ['data-testid' => 'x', 'data-n' => 3, 'data-bad' => ['nested']]],
            'templates'           => ['pagination' => 'kit/_pagination.html.twig', 'form_theme' => '', 'tabs' => '@NowoCookieConsentBundle/admin/_tabs.html.twig', 'styles_after' => 'kit/_styles.html.twig'],
        ]);

        self::assertSame('data-table', $chrome['classes']['table']);
        self::assertSame('btn-primary', $chrome['classes']['button_primary']);
        self::assertNull($chrome['classes']['pagination']);
        self::assertSame(['table_panel' => ['data-testid' => 'x', 'data-n' => '3', 'data-bad' => '']], $chrome['attributes']);
        self::assertSame('kit/_pagination.html.twig', $chrome['templates']['pagination']);
        self::assertNull($chrome['templates']['form_theme']);
        self::assertNull($chrome['templates']['tabs'], 'the removed 1.x default partial is ignored');
        self::assertSame('kit/_styles.html.twig', $chrome['templates']['styles_after']);
        self::assertSame('h2', $chrome['page_title_tag']);
        self::assertSame('text', $chrome['row_actions_display']);
    }

    public function testInvalidScalarsFallBackToDefaults(): void
    {
        $chrome = AdminChrome::resolve(['page_title_tag' => 'div', 'row_actions_display' => 'banner', 'classes' => 'nope']);

        self::assertSame('h1', $chrome['page_title_tag']);
        self::assertNull($chrome['row_actions_display']);
        self::assertSame([], $chrome['classes']);
    }

    public function testUnknownClassKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('web_ui.chrome.classes key "tabel"');

        AdminChrome::resolve(['classes' => ['tabel' => 'x']]);
    }

    public function testUnknownTemplateKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('web_ui.chrome.templates key "layout"');

        AdminChrome::resolve(['templates' => ['layout' => 'x']]);
    }

    public function testUnknownAttributeKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AdminChrome::resolve(['attributes' => ['nope' => ['a' => 'b']]]);
    }

    public function testSlotMapCoversEveryElementKeyWithExistingSlots(): void
    {
        self::assertEqualsCanonicalizing(array_keys(AdminChrome::DEFAULT_CLASSES), array_keys(AdminChrome::SLOT_MAP));
        foreach (array_keys(AdminChrome::DEFAULT_CLASSES) as $key) {
            self::assertArrayHasKey($key, AdminChrome::SLOT_MAP, $key);
            $slot = AdminChrome::SLOT_MAP[$key];
            if ($slot !== null) {
                self::assertTrue(ThemeSlots::has($slot), $key . ' => ' . $slot);
            }
        }
    }

    public function testEmptyChromeBridgesNothing(): void
    {
        self::assertSame([], AdminChrome::toUiKitTheme([]));
        self::assertSame([], AdminChrome::toUiKitTheme(['page_title_tag' => 'h2', 'row_actions_display' => 'icon', 'classes' => ['tab_list' => 'x', 'form_panel_body' => '']]));
    }

    public function testBridgesClassesAttributesAndPartialsIntoTheBundleTheme(): void
    {
        $theme = AdminChrome::toUiKitTheme([
            'classes' => [
                'page_header_form' => 'mb-4',
                'table_panel'      => 'panel',
                'table'            => ' data-table ',
                'tab_item'         => 'tab',
                'tab_item_active'  => 'is-on',
                'button_primary'   => null,
                'settings_actions' => 'settings-actions',
                'form_actions'     => 'form-actions',
            ],
            'attributes' => [
                'area_nav'     => ['data-testid' => 'area'],
                'section_tabs' => ['data-testid' => 'sections', 'data-extra' => '1'],
                'table_panel'  => ['data-testid' => 'cookie-consent-definitions-table'],
                'tab_list'     => ['data-ignored' => '1'],
            ],
            'templates' => ['tabs' => 'kit/_tabs.html.twig', 'pagination' => '@NowoCookieConsentBundle/admin/_pagination.html.twig', 'form_theme' => 'x.html.twig'],
        ]);

        self::assertSame([
            'page_header'  => ['value' => 'mb-4', 'framework_defaults' => false],
            'tab'          => ['value' => 'tab', 'framework_defaults' => false],
            'tab_active'   => ['value' => 'tab is-on', 'framework_defaults' => false],
            'table_wrap'   => ['value' => 'panel', 'framework_defaults' => false],
            'table'        => ['value' => 'data-table', 'framework_defaults' => false],
            'form_actions' => ['value' => 'form-actions', 'framework_defaults' => false],
        ], $theme['classes'] ?? null);
        self::assertSame([
            'tabs'       => ['data-testid' => 'area', 'data-extra' => '1'],
            'table_wrap' => ['data-testid' => 'cookie-consent-definitions-table'],
        ], $theme['attributes'] ?? null);
        self::assertSame(['tabs' => 'kit/_tabs.html.twig'], $theme['partials'] ?? null, 'the removed 1.x default partial is not bridged');
    }

    public function testPageHeaderWinsOverPageHeaderForm(): void
    {
        $theme = AdminChrome::toUiKitTheme(['classes' => ['page_header' => 'toolbar', 'page_header_form' => 'mb-4']]);

        self::assertSame(['page_header' => ['value' => 'toolbar', 'framework_defaults' => false]], $theme['classes'] ?? null);
    }

    public function testBeaconHostThemeBridgesEveryMappedKey(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2) . '/Fixtures/admin_chrome/host_theme.yaml');
        self::assertIsArray($config);

        $theme = AdminChrome::toUiKitTheme($config['web_ui']['chrome']);

        self::assertSame(
            ['page_header', 'page_title', 'page_intro', 'tabs', 'table_wrap', 'table', 'form_panel', 'section', 'card_body', 'form', 'form_actions', 'card_title', 'card_intro', 'btn_primary', 'btn_secondary', 'btn_danger'],
            array_keys($theme['classes'] ?? []),
        );
        self::assertSame('nowo-ui-table nowo-ui-table--striped min-w-full text-sm text-left data-table', $theme['classes']['table']['value'] ?? null);
        self::assertSame(['tabs', 'table_wrap', 'form_panel', 'section'], array_keys($theme['attributes'] ?? []));
        self::assertSame(['tabs' => '@Test/tabs.html.twig', 'pagination' => '@Test/pagination.html.twig'], $theme['partials'] ?? null);
    }
}
