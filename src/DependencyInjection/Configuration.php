<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Nowo\CookieConsentBundle\Enum\DisabledRoutesEnum;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_diff_key;
use function array_keys;
use function implode;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

/**
 * Defines the configuration tree for the cookie consent bundle.
 */
class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_cookie_consent';

    public const CSS_FRAMEWORKS = ['bootstrap', 'bootstrap4', 'bootstrap5', 'tailwind', 'foundation', 'custom', 'tabler', 'none'];

    /**
     * `web_ui.templates.<page>` => shipped page template (REQ-UI-003 per-page override).
     *
     * @var array<string, string>
     */
    public const PAGE_TEMPLATES = [
        'cookie_definition_index' => '@NowoCookieConsentBundle/admin/cookie_definition/index.html.twig',
        'cookie_definition_form'  => '@NowoCookieConsentBundle/admin/cookie_definition/form.html.twig',
        'settings'                => '@NowoCookieConsentBundle/admin/config/settings.html.twig',
    ];

    /** 1.x default of web_ui.layout_template (removed in 1.16: it loaded Bootstrap from a CDN). */
    public const LEGACY_LAYOUT_TEMPLATE = '@NowoCookieConsentBundle/admin/layout.html.twig';

    /**
     * Builds the configuration tree for bundle settings.
     *
     * @return TreeBuilder The root configuration tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('doctrine')
                    ->info('Doctrine DBAL connection and table prefix for cookie consent entities.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->info('Deprecated since 1.15 and ignored: entities are always mapped on the default entity manager/connection.')
                            ->defaultValue('default')
                            ->setDeprecated('nowo-tech/cookie-consent-bundle', '1.15', 'The "%path%.%node%" option is ignored (the bundle always uses the default Doctrine entity manager and connection) and will be removed in 2.0.')
                        ->end()
                        ->scalarNode('table_prefix')
                            ->info('Prefix prepended to table names (dashboard_cookie_log, dashboard_cookie_config, …). Empty = no prefix.')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('table_prefix')
                    ->defaultValue('')
                    ->setDeprecated('nowo-tech/cookie-consent-bundle', '1.3', 'Use doctrine.table_prefix instead.')
                    ->info('Deprecated. Use doctrine.table_prefix (e.g. "app_" yields app_dashboard_cookie_log).')
                    ->example('app_')
                ->end()
                ->arrayNode('categories')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        CategoryEnum::CATEGORY_ANALYTICS,
                        CategoryEnum::CATEGORY_MARKETING,
                        CategoryEnum::CATEGORY_PREFERENCES,
                    ])
                    ->info('Cookie categories shown in the consent modal (excluding "required").')
                ->end()
                ->booleanNode('use_logger')
                    ->defaultTrue()
                    ->info('Persist consent choices to the database when true.')
                ->end()
                ->booleanNode('use_database_config')
                    ->defaultFalse()
                    ->info('Load modal copy and display settings from CookieConsentConfig entities when true.')
                ->end()
                ->integerNode('schema_ready_cache_ttl')
                    ->defaultValue(60)
                    ->min(0)
                    ->info('Seconds a positive "consent config table exists" probe is memoized per worker process (FrankenPHP/RoadRunner). 0 probes on every main request.')
                ->end()
                ->booleanNode('use_cookie_inventory')
                    ->defaultFalse()
                    ->info('Expose cookie definitions (name, category/block, duration, provider, purpose) in the preferences modal and legal pages.')
                ->end()
                ->arrayNode('cookie_inventory')
                    ->defaultValue([])
                    ->info('Static cookie definitions when not using Doctrine entities (ignored when the active profile has CookieDefinition rows).')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('duration')->defaultValue('')->end()
                            ->scalarNode('category')->defaultValue('required')->end()
                            ->scalarNode('type')->defaultValue('first_party')->end()
                            ->integerNode('sort_order')->defaultValue(0)->end()
                            ->scalarNode('provider')->defaultNull()->end()
                            ->scalarNode('purpose')->defaultNull()->end()
                            ->arrayNode('translations')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('provider')->defaultValue('')->end()
                                        ->scalarNode('purpose')->defaultValue('')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('fetch_config_via_api')
                    ->defaultFalse()
                    ->info('Expose GET /cookie-consent/config and let the frontend script load settings via fetch().')
                ->end()
                ->booleanNode('http_only')
                    ->defaultTrue()
                    ->info('Set HttpOnly flag on consent cookies.')
                ->end()
                ->scalarNode('form_action')
                    ->defaultNull()
                    ->info('Optional route name used as the form action URL.')
                ->end()
                ->booleanNode('csrf_protection')
                    ->defaultTrue()
                    ->info('Enable CSRF protection on the consent form.')
                ->end()
                ->arrayNode('disabled_routes')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        DisabledRoutesEnum::DISABLED_ROUTE_PRIVACY,
                        DisabledRoutesEnum::DISABLED_ROUTE_IMPRINT,
                    ])
                    ->info('Route names where the modal must not open automatically.')
                ->end()
                ->scalarNode('privacy_route')
                    ->defaultNull()
                    ->info('Route name of the privacy policy linked from the modal when the profile translation leaves nowo_cookie_consent.privacy_route empty (e.g. legal_privacy of LegalKitBundle). Null = no fallback.')
                ->end()
                ->arrayNode('skip_render_routes')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->info('Route name patterns (fnmatch wildcards allowed) where the consent fragment must not render at all (empty response; no inventory/config queries). Use for authenticated dashboards/admin shells.')
                ->end()
                ->arrayNode('render_routes')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->info('When non-empty, the consent fragment renders ONLY on matching route name patterns (public whitelist). Combined with skip_render_routes (deny wins). Empty keeps previous behaviour (render everywhere except skip_render_routes).')
                ->end()
                ->enumNode('route_targeting_mode')
                    ->values(['all', 'only', 'except'])
                    ->defaultValue('all')
                    ->info('Controls where the modal auto-opens: all pages, only listed routes, or all except listed routes.')
                ->end()
                ->arrayNode('target_routes')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                    ->info('Route names used with route_targeting_mode (Symfony route names, one per line in the admin UI).')
                ->end()
                ->scalarNode('default_locale')
                    ->defaultValue('en')
                    ->info('Fallback locale when no supported language can be detected.')
                    ->example('en')
                ->end()
                ->variableNode('enabled_locales')
                    ->defaultValue(['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'])
                    ->info('Locales supported by the cookie consent UI and locale detection. A list, or a parameter / env var holding one: "%app.locales%", "%kernel.enabled_locales%", "%env(json:APP_LOCALES)%" (resolved at runtime).')
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => !is_string($v) && !self::isLocaleList($v))
                        ->thenInvalid('Invalid nowo_cookie_consent.enabled_locales %s: use a list of locale codes or a parameter / env placeholder holding one.')
                    ->end()
                ->end()
                ->booleanNode('detect_locale_from_accept_language')
                    ->defaultTrue()
                    ->info('Use the Accept-Language request header when no explicit locale is available.')
                ->end()
                ->enumNode('ui_theme')
                    ->values(['bootstrap', 'tailwind'])
                    ->defaultValue('bootstrap')
                    ->info('UI framework used by the bundled cookie consent modal templates.')
                ->end()
                ->enumNode('color_theme')
                    ->values(['light', 'dark', 'dark-turquoise', 'light-funky', 'elegant-black'])
                    ->defaultValue('light')
                ->end()
                ->booleanNode('dark_mode_enabled')->defaultFalse()->end()
                ->booleanNode('disable_transitions')->defaultFalse()->end()
                ->booleanNode('disable_page_interaction')
                    ->defaultFalse()
                    ->info('When true, adds a full-page overlay and blocks scrolling until the user chooses an option.')
                ->end()
                ->booleanNode('two_step_modal')->defaultFalse()->end()
                ->booleanNode('open_preferences_modal')->defaultFalse()->end()
                ->booleanNode('manage_iframe_placeholders')->defaultFalse()->end()
                ->booleanNode('granular_cookie_selection')
                    ->defaultFalse()
                    ->info('When true, optional cookies can be toggled individually inside each category block.')
                ->end()
                ->booleanNode('preferences_bubble_enabled')
                    ->defaultFalse()
                    ->info('Shows a floating cookie icon button to reopen the preferences modal after consent is saved.')
                ->end()
                ->enumNode('preferences_bubble_position')
                    ->values(['bottom-right', 'bottom-left', 'top-right', 'top-left'])
                    ->defaultValue('bottom-right')
                    ->info('Screen corner for the floating preferences bubble.')
                ->end()
                ->scalarNode('preferences_bubble_border_color')
                    ->defaultNull()
                    ->info('Hex color for the preferences bubble border and cookie icon (e.g. #30363c).')
                ->end()
                ->scalarNode('preferences_bubble_icon')
                    ->defaultNull()
                    ->info('Custom inline SVG (or simple inline HTML such as an emoji span) for the preferences bubble icon, well-formed XML, allowlist-sanitized. Leave empty for the default cookie SVG.')
                    ->validate()
                        ->ifTrue(static fn ($v): bool => is_string($v) && PreferencesBubbleIconSanitizer::sanitizeOrNull($v) === null && trim($v) !== '')
                        ->thenInvalid('preferences_bubble_icon must be well-formed SVG/inline HTML without scripts, event handlers or external references.')
                    ->end()
                ->end()
                ->arrayNode('preference_sections')
                    ->defaultValue([])
                    ->info('Optional preference modal sections (title, description, categories).')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->defaultValue('')->end()
                            ->scalarNode('description')->defaultValue('')->end()
                            ->arrayNode('categories')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static fn (array $v): array => self::normalizeWebUi($v))
                    ->end()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('When false, admin access subscriber is not registered (routes still load; prefer security firewall).')
                        ->end()
                        ->scalarNode('path_prefix')
                            ->defaultValue('/cookie-consent-config')
                            ->cannotBeEmpty()
                            ->info('Deprecated since 1.15 and ignored: admin routes are always mounted under /cookie-consent-config (use the import prefix of the bundle routes to move them).')
                            ->setDeprecated('nowo-tech/cookie-consent-bundle', '1.15', 'The "%path%.%node%" option is ignored (admin routes are always /cookie-consent-config/...; use the "prefix" of the routes import to move them) and will be removed in 2.0.')
                            ->validate()
                                ->ifTrue(static fn ($v): bool => !is_string($v) || !str_starts_with($v, '/'))
                                ->thenInvalid('path_prefix must be a string starting with /')
                            ->end()
                        ->end()
                        ->scalarNode('layout_template')
                            ->defaultNull()
                            ->info('Per-bundle layout override of the admin pages (REQ-UI-003). Null (default) = the UiKit admin layout (nowo_ui_kit.layouts.admin). The layout must render the UiKit canonical blocks (nowo_ui_content, nowo_ui_page_header, …).')
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template override (REQ-UI-003): ' . implode(', ', array_keys(self::PAGE_TEMPLATES)) . '. Use a thin template that extends the shipped one ("@!NowoCookieConsentBundle/…") and overrides a data block.')
                            ->useAttributeAsKey('page')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff_key($v, self::PAGE_TEMPLATES) !== [])
                                ->then(static function (array $v): never {
                                    throw new InvalidArgumentException(sprintf('Unknown web_ui.templates page(s): %s. Known pages: %s.', implode(', ', array_keys(array_diff_key($v, self::PAGE_TEMPLATES))), implode(', ', array_keys(self::PAGE_TEMPLATES))));
                                })
                            ->end()
                        ->end()
                        ->enumNode('css_framework')
                            ->values([...self::CSS_FRAMEWORKS, null])
                            ->defaultNull()
                            ->info('Deprecated since 1.16: set nowo_ui_kit.css_framework. When set (and nowo_ui_kit.css_framework is not), seeds the UiKit stack.')
                            ->setDeprecated('nowo-tech/cookie-consent-bundle', '1.16', 'The "%path%.%node%" option is deprecated: the admin pages inherit nowo_ui_kit.css_framework. It will be removed in 2.0.')
                        ->end()
                        ->enumNode('icon_set')
                            ->values(['bootstrap-icons', 'tabler-icons', 'ux_icon', 'svg_inline', 'none', null])
                            ->defaultNull()
                            ->info('Deprecated since 1.16: set nowo_ui_kit.icon_set. When set (and nowo_ui_kit.icon_set is not), seeds the UiKit icon set.')
                            ->setDeprecated('nowo-tech/cookie-consent-bundle', '1.16', 'The "%path%.%node%" option is deprecated: the admin pages inherit nowo_ui_kit.icon_set. It will be removed in 2.0.')
                        ->end()
                        ->integerNode('list_page_size')
                            ->defaultValue(20)
                            ->min(1)
                            ->max(200)
                            ->info('Default page size for admin cookie definition lists.')
                        ->end()
                        ->arrayNode('chrome')
                            ->info('Deprecated since 1.16 (1.13 admin chrome hooks): bridged into nowo_ui_kit.theme.bundles.NowoCookieConsentBundle; see docs/UPGRADING.md "Template contract".')
                            ->setDeprecated('nowo-tech/cookie-consent-bundle', '1.16', 'The "%path%.%node%" option is deprecated: the admin pages use the UiKit page scaffolds and theme slots; configure nowo_ui_kit.theme.bundles.NowoCookieConsentBundle (see UPGRADING "Template contract"). It will be removed in 2.0.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('page_title_tag')
                                    ->values(AdminChrome::PAGE_TITLE_TAGS)
                                    ->defaultValue('h1')
                                    ->info('Ignored since 1.16: the heading comes from the UiKit page_header partial (override it through theme.partials.page_header).')
                                ->end()
                                ->enumNode('row_actions_display')
                                    ->values(AdminChrome::ROW_ACTIONS_DISPLAYS)
                                    ->defaultValue('inherit')
                                    ->info('Row actions display of the cookie list: text | icon | icon_text | inherit (default: nowo_ui_kit.row_actions_display).')
                                ->end()
                                ->arrayNode('classes')
                                    ->info('1.x element key => classes (see AdminChrome::SLOT_MAP), bridged into the UiKit theme slots.')
                                    ->useAttributeAsKey('name')
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()->end()
                                    ->validate()
                                        ->ifTrue(static fn (array $v): bool => array_diff_key($v, AdminChrome::DEFAULT_CLASSES) !== [])
                                        ->then(static function (array $v): never {
                                            throw new InvalidArgumentException(sprintf('Unknown web_ui.chrome.classes key(s): %s. Known keys: %s.', implode(', ', array_keys(array_diff_key($v, AdminChrome::DEFAULT_CLASSES))), implode(', ', array_keys(AdminChrome::DEFAULT_CLASSES))));
                                        })
                                    ->end()
                                ->end()
                                ->arrayNode('attributes')
                                    ->info('1.x element key (same keys as classes) => extra HTML attributes, bridged into the UiKit theme slot attributes.')
                                    ->useAttributeAsKey('name')
                                    ->normalizeKeys(false)
                                    ->arrayPrototype()
                                        ->useAttributeAsKey('name')
                                        ->normalizeKeys(false)
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->validate()
                                        ->ifTrue(static fn (array $v): bool => array_diff_key($v, AdminChrome::DEFAULT_CLASSES) !== [])
                                        ->then(static function (array $v): never {
                                            throw new InvalidArgumentException(sprintf('Unknown web_ui.chrome.attributes key(s): %s. Known keys: %s.', implode(', ', array_keys(array_diff_key($v, AdminChrome::DEFAULT_CLASSES))), implode(', ', array_keys(AdminChrome::DEFAULT_CLASSES))));
                                        })
                                    ->end()
                                ->end()
                                ->arrayNode('templates')
                                    ->info('Partial paths: tabs / pagination (bridged to the UiKit partials), form_theme, form_fields, styles_after (still applied by the admin pages).')
                                    ->useAttributeAsKey('name')
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()->end()
                                    ->validate()
                                        ->ifTrue(static fn (array $v): bool => array_diff_key($v, AdminChrome::DEFAULT_TEMPLATES) !== [])
                                        ->then(static function (array $v): never {
                                            throw new InvalidArgumentException(sprintf('Unknown web_ui.chrome.templates key(s): %s. Known keys: %s.', implode(', ', array_keys(array_diff_key($v, AdminChrome::DEFAULT_TEMPLATES))), implode(', ', array_keys(AdminChrome::DEFAULT_TEMPLATES))));
                                        })
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('access_checker')
                            ->defaultNull()
                            ->info('Optional service id implementing CookieConsentAccessCheckerInterface.')
                        ->end()
                        ->arrayNode('access_roles')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                            ->info('Roles that may access admin CRUD. Empty list is fail-closed (deny via access checker). Use allow_unauthenticated for demos only.')
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->defaultFalse()
                            ->info('DEV/DEMO ONLY. When true, admin UI may load without SecurityBundle / without login. Production MUST keep false.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * `web_ui` before normalization: the 1.x default layout (`@NowoCookieConsentBundle/admin/layout.html.twig`,
     * removed in 1.16) set explicitly, or an empty string, means "the UiKit layout" (null).
     *
     * @param array<string, mixed> $webUi
     *
     * @return array<string, mixed>
     */
    private static function normalizeWebUi(array $webUi): array
    {
        $layout = $webUi['layout_template'] ?? null;
        if (is_string($layout) && (trim($layout) === '' || $layout === self::LEGACY_LAYOUT_TEMPLATE)) {
            $webUi['layout_template'] = null;
        }

        return $webUi;
    }

    private static function isLocaleList(mixed $v): bool
    {
        if (!is_array($v)) {
            return false;
        }
        foreach ($v as $locale) {
            if (!is_string($locale)) {
                return false;
            }
        }

        return true;
    }
}
