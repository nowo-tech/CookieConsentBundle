<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use DateInterval;
use Exception;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\Config\PreferencesBubbleIconSanitizer;
use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Nowo\CookieConsentBundle\Enum\DisabledRoutesEnum;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

use function array_diff_key;
use function array_key_exists;
use function array_keys;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function strpos;
use function substr;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

/**
 * Defines the configuration tree for the cookie consent bundle.
 */
final class Configuration implements ConfigurationInterface
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

    /**
     * 2.0: keys removed with their replacement (UPGRADING "To 2.0.0"). A host config that still sets one fails the build
     * with this message instead of a generic "unrecognized option".
     *
     * @var array<string, string>
     */
    public const REMOVED_KEYS = [
        'table_prefix'         => 'use "nowo_cookie_consent.doctrine.table_prefix" (deprecated since 1.3)',
        'doctrine.connection'  => 'use "nowo_cookie_consent.doctrine.entity_manager" to map the bundle entities on another entity manager (ignored since 1.15)',
        'web_ui.path_prefix'   => 'use the "prefix" of the bundle routes import (ignored since 1.15)',
        'web_ui.css_framework' => 'set "nowo_ui_kit.css_framework" (deprecated since 1.16)',
        'web_ui.icon_set'      => 'set "nowo_ui_kit.icon_set" (deprecated since 1.16)',
        'web_ui.chrome'        => 'configure "nowo_ui_kit.theme.bundles.NowoCookieConsentBundle" (UiKit theme slots, partials and page scaffolds; deprecated since 1.16)',
    ];

    /** 2.0: the build error of `doctrine.private_entity_manager: false` (the option no longer turns the private manager off). */
    public const PRIVATE_ENTITY_MANAGER_FALSE_REMOVED = '"nowo_cookie_consent.doctrine.private_entity_manager: false" was removed in 2.0: profiles and cookie definitions on your default entity manager are always written through the bundle\'s private entity manager (same connection and tables), which never flushes your manager. Remove the key (a dedicated "doctrine.entity_manager" keeps working unchanged). See UPGRADING.md "To 2.0.0".';

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
            ->beforeNormalization()
                ->ifArray()
                ->then(static fn (array $v): array => self::refuseRemovedKeys($v))
            ->end();
        $this->addDoctrineToUseCookieInventorySections($rootNode);
        $this->addCookieInventoryToRouteTargetingModeSections($rootNode);
        $this->addTargetRoutesToPreferenceSectionsSections($rootNode);
        $this->addWebUiSection($rootNode);
        $this->addSecurityToConflictResponseSections($rootNode);

        return $treeBuilder;
    }

    /**
     * Adds the `doctrine` to `use_cookie_inventory` settings.
     */
    private function addDoctrineToUseCookieInventorySections(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('doctrine')
                    ->info('Doctrine DBAL connection and table prefix for cookie consent entities.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('entity_manager')
                            ->info('Name of the Doctrine entity manager the bundle entities are mapped on (e.g. a dedicated "cookie_consent" manager). Null = the default entity manager.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('private_entity_manager')
                            ->info('Since 2.0 the bundle always reads and writes its profiles and cookie definitions through a private entity manager of its own, created on the connection, mapping and event manager of your default entity manager: same tables, and a profile or cookie definition save never flushes your manager. true = no effect (deprecated, removed in 3.0; with a dedicated doctrine.entity_manager it keeps the 1.34 meaning: the private manager is created on that manager). false = build error (removed in 2.0). Null (default) = the private manager, or the dedicated doctrine.entity_manager as is.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn ($v): bool => $v === false)
                                ->then(static function (): never {
                                    throw new InvalidArgumentException(self::PRIVATE_ENTITY_MANAGER_FALSE_REMOVED);
                                })
                            ->end()
                        ->end()
                        ->scalarNode('table_prefix')
                            ->info('Prefix prepended to table names (dashboard_cookie_log, dashboard_cookie_config, …). Empty = no prefix.')
                            ->defaultValue('')
                        ->end()
                    ->end()
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
            ->end();
    }

    /**
     * Adds the `cookie_inventory` to `route_targeting_mode` settings.
     */
    private function addCookieInventoryToRouteTargetingModeSections(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('cookie_inventory')
                    ->defaultValue([])
                    ->info('Static cookie definitions when not using Doctrine entities (ignored when the active profile has CookieDefinition rows).')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifTrue(static fn (mixed $entry): bool => is_array($entry) && array_key_exists('allowedByDefault', $entry))
                            ->then(static function (array $entry): array {
                                $entry['allowed_by_default'] ??= $entry['allowedByDefault'];
                                unset($entry['allowedByDefault']);

                                return $entry;
                            })
                        ->end()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('duration')->defaultValue('')->end()
                            ->scalarNode('category')->defaultValue('required')->end()
                            ->scalarNode('type')->defaultValue('first_party')->end()
                            ->integerNode('sort_order')->defaultValue(0)->end()
                            ->scalarNode('provider')->defaultNull()->end()
                            ->scalarNode('purpose')->defaultNull()->end()
                            ->scalarNode('allowed_by_default')
                                ->defaultNull()
                                ->info('Pre-tick this cookie before consent is saved (granular selection). Null (default) means false, or true for the "required" category (JD-L6). Alias: allowedByDefault.')
                                ->validate()
                                    ->ifTrue(static fn (mixed $value): bool => $value !== null && !is_bool($value) && filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null)
                                    ->thenInvalid('allowed_by_default must be a boolean (true/false), got %s.')
                                ->end()
                            ->end()
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
            ->end();
    }

    /**
     * Adds the `target_routes` to `preference_sections` settings.
     */
    private function addTargetRoutesToPreferenceSectionsSections(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
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
            ->end();
    }

    /**
     * Adds the `enabled` to `list_page_size` settings.
     */
    private function addWebUiEnabledToListPageSizeSections(ArrayNodeDefinition $webUi): void
    {
        $webUi
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('When false, admin access subscriber is not registered (routes still load; prefer security firewall).')
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
                ->integerNode('list_page_size')
                    ->defaultValue(20)
                    ->min(1)
                    ->max(200)
                    ->info('Default page size for admin cookie definition lists.')
                ->end()
            ->end();
    }

    /**
     * Adds the `web_ui` section; its large children have their own methods.
     */
    private function addWebUiSection(ArrayNodeDefinition $rootNode): void
    {
        $webUi = $rootNode->children()->arrayNode('web_ui');
        $webUi
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->ifArray()
                ->then(static fn (array $v): array => self::normalizeWebUi($v))
            ->end();
        $this->addWebUiEnabledToListPageSizeSections($webUi);
    }

    /**
     * Adds the `security` to `conflict_response` settings.
     */
    private function addSecurityToConflictResponseSections(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
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
                ->arrayNode('personal_data')
                    ->info('GDPR erasure / export of the consent log (1.29). The ports always exist; this node drives the UserKit bridge.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Register the UserKit eraser (cookie_consent_logs) and export provider (cookie_consents) when UserKit is installed and the bundle entities are mapped. Set false when your app already erases the consent log.')
                        ->end()
                        ->enumNode('ip_windows')
                            ->values(['off', 'erase'])
                            ->defaultValue('off')
                            ->info('Erasure by anonymised client IP and time window: "off" (default) erases by consent key only; "erase" also deletes the rows of the IP windows (they match every visitor of the same anonymised /24 or /48 in the window). IP windows are never exported.')
                        ->end()
                        ->scalarNode('ip_window_margin')
                            ->defaultValue('PT0S')
                            ->cannotBeEmpty()
                            ->info('ISO 8601 duration added before and after every IP window an ip_window_provider returns (default none; only with ip_windows: erase).')
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => !is_string($v) || !self::isDuration($v))
                                ->thenInvalid('personal_data.ip_window_margin must be an ISO 8601 duration such as "P1D" or "PT12H", got %s.')
                            ->end()
                        ->end()
                        ->scalarNode('subject_resolver')
                            ->defaultNull()
                            ->info('Optional service id implementing CookieConsentPersonalDataSubjectResolverInterface (default: the IP windows of the tagged ip_window_provider services, used only with ip_windows: erase).')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('conflict_response')
                    ->info('1.31 (REQ-RUNTIME-003): answer of a lock conflict (deadlock, lock wait timeout, serialization failure) of the consent POST and the bundle\'s routes. Unset: nowo-tech/transaction-doctrine\'s conflict_response when that bundle is registered, else 409 with Retry-After 1.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('status')
                            ->info('409 (default, CONFLICT_001 / CONFLICT_002) or 503. Null: follow transaction-doctrine, else 409.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !in_array($value, ConflictResponsePolicy::STATUSES, true))
                                ->thenInvalid('nowo_cookie_consent.conflict_response.status must be 409 or 503, got %s.')
                            ->end()
                        ->end()
                        ->integerNode('retry_after')
                            ->info('Retry-After seconds of a CONFLICT_001 answer. Null: follow transaction-doctrine, else 1.')
                            ->defaultNull()
                            ->min(0)
                        ->end()
                        ->enumNode('commit_tracking')
                            ->values(['bundle', 'all', 'off'])
                            ->defaultValue('bundle')
                            ->info('Commits counted for CONFLICT_002 when nowo-tech/transaction-doctrine\'s commit_detection is off: bundle (default, the connection of the bundle\'s entity manager only), all (every Doctrine connection) or off (no middleware; only the failure itself and transaction-doctrine\'s request mark decide). With commit_detection on, transaction-doctrine\'s ledger is reused and the bundle adds no middleware.')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * 2.0: fails with a message naming the replacement when the host config still sets a key removed in 2.0
     * ({@see self::REMOVED_KEYS}), instead of Symfony's generic "unrecognized option".
     *
     * @param array<mixed> $config one raw `nowo_cookie_consent` config array
     *
     * @return array<mixed>
     */
    private static function refuseRemovedKeys(array $config): array
    {
        foreach (self::REMOVED_KEYS as $path => $replacement) {
            $dot     = strpos($path, '.');
            $section = $dot === false ? null : substr($path, 0, $dot);
            $key     = $dot === false ? $path : substr($path, $dot + 1);
            $node    = $section === null ? $config : ($config[$section] ?? null);
            if (is_array($node) && array_key_exists($key, $node)) {
                throw new InvalidConfigurationException(sprintf('The "nowo_cookie_consent.%s" option was removed in 2.0: %s. See UPGRADING.md "To 2.0.0".', $path, $replacement));
            }
        }

        return $config;
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

    private static function isDuration(string $value): bool
    {
        try {
            new DateInterval($value);
        } catch (Exception) {
            return false;
        }

        return true;
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
