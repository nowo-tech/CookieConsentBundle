<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Admin;

use InvalidArgumentException;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;
use function trim;

/**
 * Admin Web UI chrome hooks of 1.13–1.15 (`web_ui.chrome`: class map, attribute map, partial paths).
 *
 * @deprecated since 1.16, the admin pages extend the UiKit page scaffolds (REQ-UI-003) and read the UiKit theme
 *             slots. The `web_ui.chrome` keys are bridged into `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle`
 *             by {@see self::toUiKitTheme()}; this class and the keys are removed in 2.0.
 */
final class AdminChrome
{
    /** UiKit theme exception key of this bundle (`nowo_ui_kit.theme.bundles.<key>`). */
    public const BUNDLE = 'NowoCookieConsentBundle';

    /**
     * 1.x element keys (`web_ui.chrome.classes|attributes`). Values: the 1.x default classes, kept for reference;
     * the scaffolds paint every element from the UiKit theme slots.
     *
     * @var array<string, string|null>
     */
    public const DEFAULT_CLASSES = [
        // Page header (list + form pages)
        'page_header'      => 'nowo-ui-page-header d-flex justify-content-between align-items-center mb-4',
        'page_header_form' => 'nowo-ui-page-header mb-4',
        'page_title'       => 'h3 mb-1',
        'page_intro'       => 'text-muted mb-0',
        // Tabs (area nav + settings section tabs)
        'area_nav'        => 'nowo-ui-tabs nowo-ui-tabs--area mb-3',
        'section_tabs'    => 'nowo-ui-tabs nowo-ui-tabs--sections mb-4',
        'tab_list'        => 'nowo-ui-tabs__list',
        'tab_item'        => 'nowo-ui-tabs__item',
        'tab_item_active' => 'is-active',
        // Cookie inventory table
        'table_panel'        => 'nowo-ui-table-wrap card shadow-sm',
        'table_wrap'         => 'table-responsive',
        'table'              => 'table table-striped mb-0 align-middle nowo-ui-table',
        'table_muted_cell'   => 'small text-secondary',
        'table_actions_cell' => 'text-end',
        'empty_cell'         => 'text-center text-muted py-4',
        // Forms (cookie definition + settings)
        'form_panel'          => 'card shadow-sm nowo-ui-form-wrap',
        'form_panel_body'     => 'card-body',
        'settings_panel'      => 'card shadow-sm nowo-ui-form-wrap',
        'settings_panel_body' => 'card-body p-4',
        'form'                => '',
        'form_actions'        => 'd-flex gap-2 mt-3',
        'settings_actions'    => 'nowo-ui-form-actions mt-4 pt-3 border-top',
        'section_title'       => 'h6 text-uppercase text-muted fw-semibold mb-1',
        'section_lead'        => 'small text-muted mb-3',
        // Buttons
        'button_primary'   => null,
        'button_secondary' => null,
        'confirm_cancel'   => null,
        'confirm_delete'   => null,
        // Pagination
        'pagination'         => null,
        'pagination_summary' => 'text-muted small',
        'pagination_actions' => null,
        'pagination_button'  => null,
    ];

    /**
     * 1.x element key => UiKit theme slot (null = no equivalent slot: the value is ignored). When several 1.x keys
     * map to one slot (of the same page scope), the later key of this list wins for classes and the attributes are
     * merged in this order.
     *
     * @var array<string, string|null>
     */
    public const SLOT_MAP = [
        'page_header_form'    => 'page_header',
        'page_header'         => 'page_header',
        'page_title'          => 'page_title',
        'page_intro'          => 'page_intro',
        'area_nav'            => 'tabs',
        'section_tabs'        => 'tabs',
        'tab_list'            => null,
        'tab_item'            => 'tab',
        'tab_item_active'     => 'tab_active',
        'table_panel'         => 'table_wrap',
        'table_wrap'          => 'table_wrap',
        'table'               => 'table',
        'table_muted_cell'    => 'table_cell_muted',
        'table_actions_cell'  => 'table_actions_cell',
        'empty_cell'          => 'empty',
        'form_panel'          => 'form_panel',
        'form_panel_body'     => null,
        'settings_panel'      => 'section',
        'settings_panel_body' => 'card_body',
        'form'                => 'form',
        'settings_actions'    => 'form_actions',
        'form_actions'        => 'form_actions',
        'section_title'       => 'card_title',
        'section_lead'        => 'card_intro',
        'button_primary'      => 'btn_primary',
        'button_secondary'    => 'btn_secondary',
        'confirm_cancel'      => null,
        'confirm_delete'      => 'btn_danger',
        'pagination'          => 'pagination',
        'pagination_summary'  => 'pagination_info',
        'pagination_actions'  => null,
        'pagination_button'   => 'pagination_link',
    ];

    /**
     * 1.x element keys that painted one admin page only => UiKit page type: bridged into
     * `theme.bundles.NowoCookieConsentBundle.pages.<type>` (UiKit 2.1) instead of the bundle-wide slot, so e.g.
     * `page_header` (cookie inventory) and `page_header_form` (cookie definition form) keep their own classes.
     *
     * @var array<string, string>
     */
    public const PAGE_SCOPED = [
        'page_header'      => 'list',
        'page_header_form' => 'form',
        'form_actions'     => 'form',
        'settings_actions' => 'settings',
    ];

    /**
     * 1.x partial / theme keys (`web_ui.chrome.templates`); null = not set. `tabs` and `pagination` are bridged to
     * the UiKit partials of the same name; `form_theme` becomes the admin forms' `formThemes` (UiKit 2.1);
     * `form_fields` and `styles_after` are still applied by the admin templates.
     *
     * @var array<string, null>
     */
    public const DEFAULT_TEMPLATES = [
        'tabs'         => null,
        'pagination'   => null,
        'form_theme'   => null,
        'form_fields'  => null,
        'styles_after' => null,
    ];

    /** 1.x shipped partials, gone in 1.16: a host value equal to one of them is ignored. */
    public const REMOVED_TEMPLATES = [
        '@NowoCookieConsentBundle/admin/_tabs.html.twig',
        '@NowoCookieConsentBundle/admin/_pagination.html.twig',
    ];

    public const PAGE_TITLE_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'none'];

    public const ROW_ACTIONS_DISPLAYS = ['text', 'icon', 'icon_text', 'inherit'];

    /**
     * Builds the deprecated Twig global `nowo_cookie_consent_admin_chrome` from (partial) bundle config: only the
     * values a host configured explicitly (classes / attributes / templates) plus the two scalar options.
     *
     * @param array<string, mixed> $config `web_ui.chrome` subtree (may be empty)
     *
     * @return array{classes: array<string, string|null>, attributes: array<string, array<string, string>>, templates: array<string, string|null>, page_title_tag: string, row_actions_display: string|null}
     */
    public static function resolve(array $config = []): array
    {
        $classes = [];
        foreach (self::mapOf($config['classes'] ?? []) as $key => $value) {
            $key = (string) $key;
            self::assertKnownKey($key, self::DEFAULT_CLASSES, 'classes');
            $classes[$key] = $value === null ? null : (string) $value;
        }

        $templates = self::DEFAULT_TEMPLATES;
        foreach (self::mapOf($config['templates'] ?? []) as $key => $value) {
            $key = (string) $key;
            self::assertKnownKey($key, self::DEFAULT_TEMPLATES, 'templates');
            $value           = ($value === null || $value === '') ? null : (string) $value;
            $templates[$key] = in_array($value, self::REMOVED_TEMPLATES, true) ? null : $value;
        }

        $attributes = [];
        foreach (self::mapOf($config['attributes'] ?? []) as $key => $attrs) {
            $key = (string) $key;
            self::assertKnownKey($key, self::DEFAULT_CLASSES, 'attributes');
            $attributes[$key] = [];
            foreach (self::mapOf($attrs) as $name => $value) {
                $attributes[$key][(string) $name] = is_scalar($value) ? (string) $value : '';
            }
        }

        $tag = $config['page_title_tag'] ?? 'h1';
        if (!is_string($tag) || !in_array($tag, self::PAGE_TITLE_TAGS, true)) {
            $tag = 'h1';
        }

        $display = $config['row_actions_display'] ?? 'inherit';
        if (!is_string($display) || !in_array($display, self::ROW_ACTIONS_DISPLAYS, true)) {
            $display = 'inherit';
        }

        return [
            'classes'        => $classes,
            'attributes'     => $attributes,
            'templates'      => $templates,
            'page_title_tag' => $tag,
            // null = UiKit's nowo_ui_kit.row_actions_display.
            'row_actions_display' => $display === 'inherit' ? null : $display,
        ];
    }

    /**
     * Bridges an explicit `web_ui.chrome` config into the UiKit theme exception of this bundle
     * (`nowo_ui_kit.theme.bundles.NowoCookieConsentBundle`). 1.x class strings replaced the whole default class
     * list, so each bridged slot drops the UiKit framework defaults (`framework_defaults: false`): the elements keep
     * the host classes they had. Keys of one page ({@see self::PAGE_SCOPED}) go to `pages.<type>`. Empty result =
     * nothing to bridge.
     *
     * @param array<string, mixed> $config `web_ui.chrome` subtree
     *
     * @return array{classes?: array<string, array{value: string, framework_defaults: false}>, attributes?: array<string, array<string, string>>, partials?: array<string, string>, pages?: array<string, array{classes?: array<string, array{value: string, framework_defaults: false}>, attributes?: array<string, array<string, string>>}>}
     */
    public static function toUiKitTheme(array $config): array
    {
        $chrome = self::resolve($config);

        // [page type|'' => [slot => entry]]; '' = bundle-wide.
        $classes    = [];
        $attributes = [];
        foreach (self::SLOT_MAP as $key => $slot) {
            if ($slot === null) {
                continue;
            }
            $scope = self::PAGE_SCOPED[$key] ?? '';

            $value = $chrome['classes'][$key] ?? null;
            if ($value !== null) {
                $value = trim($value);
                if ($slot === 'tab_active' && isset($chrome['classes']['tab_item'])) {
                    // 1.x printed tab_item + tab_item_active on the current tab; the tab_active slot replaces tab.
                    $value = trim($chrome['classes']['tab_item'] . ' ' . $value);
                }
                $classes[$scope][$slot] = ['value' => $value, 'framework_defaults' => false];
            }

            if (isset($chrome['attributes'][$key])) {
                $attributes[$scope][$slot] = ($attributes[$scope][$slot] ?? []) + $chrome['attributes'][$key];
            }
        }

        $partials = [];
        foreach (['tabs', 'pagination'] as $partial) {
            if ($chrome['templates'][$partial] !== null) {
                $partials[$partial] = $chrome['templates'][$partial];
            }
        }

        $theme = [];
        if (isset($classes[''])) {
            $theme['classes'] = $classes[''];
        }
        if (isset($attributes[''])) {
            $theme['attributes'] = $attributes[''];
        }
        if ($partials !== []) {
            $theme['partials'] = $partials;
        }
        foreach (array_unique(array_values(self::PAGE_SCOPED)) as $page) {
            if (isset($classes[$page])) {
                $theme['pages'][$page]['classes'] = $classes[$page];
            }
            if (isset($attributes[$page])) {
                $theme['pages'][$page]['attributes'] = $attributes[$page];
            }
        }

        return $theme;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function mapOf(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $known
     */
    private static function assertKnownKey(int|string $key, array $known, string $section): void
    {
        if (!array_key_exists((string) $key, $known)) {
            throw new InvalidArgumentException(sprintf('Unknown web_ui.chrome.%s key "%s". Known keys: %s.', $section, (string) $key, implode(', ', array_keys($known))));
        }
    }
}
