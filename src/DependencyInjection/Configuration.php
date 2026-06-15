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
            ->end();

        return $treeBuilder;
    }
}
