<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Admin;

use InvalidArgumentException;

use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Admin Web UI chrome hooks (FR-010 / spec 002): class map, attribute map, partial paths.
 *
 * Every default reproduces the markup shipped up to 1.12.x, so hosts that do not configure
 * `web_ui.chrome` render byte-identical admin pages. A `null` class means "derive from the
 * UiKit macro for the configured css_framework" (buttons, pagination wrappers).
 */
final class AdminChrome
{
    /**
     * Element key => default CSS class string (null = UiKit macro for web_ui.css_framework).
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
        // Buttons (null = ui.btn(variant, size, css_framework))
        'button_primary'   => null,
        'button_secondary' => null,
        'confirm_cancel'   => null,
        'confirm_delete'   => null,
        // Pagination (null = ui.pagination_wrap / ui.row_actions / ui.btn)
        'pagination'         => null,
        'pagination_summary' => 'text-muted small',
        'pagination_actions' => null,
        'pagination_button'  => null,
    ];

    /**
     * Partial / theme key => default template (null = feature off).
     *
     * @var array<string, string|null>
     */
    public const DEFAULT_TEMPLATES = [
        'tabs'         => '@NowoCookieConsentBundle/admin/_tabs.html.twig',
        'pagination'   => '@NowoCookieConsentBundle/admin/_pagination.html.twig',
        'form_theme'   => 'bootstrap_5_layout.html.twig',
        'form_fields'  => null,
        'styles_after' => null,
    ];

    public const PAGE_TITLE_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'none'];

    public const ROW_ACTIONS_DISPLAYS = ['text', 'icon', 'icon_text', 'inherit'];

    /**
     * Builds the Twig global `nowo_cookie_consent_admin_chrome` from (partial) bundle config.
     *
     * @param array<string, mixed> $config `web_ui.chrome` subtree (may be empty)
     *
     * @return array{classes: array<string, string|null>, attributes: array<string, array<string, string>>, templates: array<string, string|null>, page_title_tag: string, row_actions_display: string|null}
     */
    public static function resolve(array $config = []): array
    {
        $classes = self::DEFAULT_CLASSES;
        foreach (self::mapOf($config['classes'] ?? []) as $key => $value) {
            $key = (string) $key;
            self::assertKnownKey($key, self::DEFAULT_CLASSES, 'classes');
            $classes[$key] = $value === null ? null : (string) $value;
        }

        $templates = self::DEFAULT_TEMPLATES;
        foreach (self::mapOf($config['templates'] ?? []) as $key => $value) {
            $key = (string) $key;
            self::assertKnownKey($key, self::DEFAULT_TEMPLATES, 'templates');
            $templates[$key] = ($value === null || $value === '') ? null : (string) $value;
        }
        foreach (['tabs', 'pagination'] as $required) {
            $templates[$required] ??= self::DEFAULT_TEMPLATES[$required];
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

        $display = $config['row_actions_display'] ?? 'text';
        if (!is_string($display) || !in_array($display, self::ROW_ACTIONS_DISPLAYS, true)) {
            $display = 'text';
        }

        return [
            'classes'        => $classes,
            'attributes'     => $attributes,
            'templates'      => $templates,
            'page_title_tag' => $tag,
            // null lets UiKit _row_actions fall back to nowo_ui_kit.row_actions_display.
            'row_actions_display' => $display === 'inherit' ? null : $display,
        ];
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
