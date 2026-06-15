<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use Nowo\CookieConsentBundle\Enum\CategoryEnum;
use Nowo\CookieConsentBundle\Enum\DisabledRoutesEnum;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the configuration tree for the cookie consent bundle.
 */
class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_cookie_consent';

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
                ->scalarNode('table_prefix')
                    ->defaultValue('')
                    ->info('Optional prefix for Doctrine entity table names (e.g. "app_" yields "app_cookie_consent_log").')
                    ->example('app_')
                ->end()
                ->variableNode('categories')
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
                ->variableNode('disabled_routes')
                    ->defaultValue([
                        DisabledRoutesEnum::DISABLED_ROUTE_PRIVACY,
                        DisabledRoutesEnum::DISABLED_ROUTE_IMPRINT,
                    ])
                    ->info('Route names where the modal must not open automatically.')
                ->end()
                ->enumNode('route_targeting_mode')
                    ->values(['all', 'only', 'except'])
                    ->defaultValue('all')
                    ->info('Controls where the modal auto-opens: all pages, only listed routes, or all except listed routes.')
                ->end()
                ->variableNode('target_routes')
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
                    ->info('Locales supported by the cookie consent UI and locale detection.')
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
                ->variableNode('preference_sections')->defaultValue([])->end()
            ->end();

        return $treeBuilder;
    }
}
