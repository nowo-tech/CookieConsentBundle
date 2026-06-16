<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use Nowo\CookieConsentBundle\Config\CookieInventoryNormalizer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Loader\ArrayLoader;

/**
 * Loads bundle configuration and registers services in the container.
 */
class NowoCookieConsentExtension extends Extension
{
    /**
     * Processes configuration and loads service definitions.
     *
     * @param array<int, array<string, mixed>> $configs The bundle configuration arrays
     * @param ContainerBuilder $container The service container builder
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('nowo_cookie_consent.table_prefix', $config['table_prefix']);
        $container->setParameter('nowo_cookie_consent.categories', $config['categories']);
        $container->setParameter('nowo_cookie_consent.use_logger', $config['use_logger']);
        $container->setParameter('nowo_cookie_consent.use_database_config', $config['use_database_config']);
        $container->setParameter('nowo_cookie_consent.use_cookie_inventory', $config['use_cookie_inventory']);
        $container->setParameter(
            'nowo_cookie_consent.cookie_inventory',
            CookieInventoryNormalizer::normalize($config['cookie_inventory']),
        );
        $container->setParameter('nowo_cookie_consent.fetch_config_via_api', $config['fetch_config_via_api']);
        $container->setParameter('nowo_cookie_consent.http_only', $config['http_only']);
        $container->setParameter('nowo_cookie_consent.form_action', $config['form_action']);
        $container->setParameter('nowo_cookie_consent.csrf_protection', $config['csrf_protection']);
        $container->setParameter('nowo_cookie_consent.disabled_routes', $config['disabled_routes']);
        $container->setParameter('nowo_cookie_consent.route_targeting_mode', $config['route_targeting_mode']);
        $container->setParameter('nowo_cookie_consent.target_routes', $config['target_routes']);
        $container->setParameter('nowo_cookie_consent.default_locale', $config['default_locale']);
        $container->setParameter('nowo_cookie_consent.enabled_locales', $config['enabled_locales']);
        $container->setParameter('nowo_cookie_consent.detect_locale_from_accept_language', $config['detect_locale_from_accept_language']);
        $container->setParameter('nowo_cookie_consent.ui_theme', $config['ui_theme']);
        $container->setParameter('nowo_cookie_consent.color_theme', $config['color_theme']);
        $container->setParameter('nowo_cookie_consent.dark_mode_enabled', $config['dark_mode_enabled']);
        $container->setParameter('nowo_cookie_consent.disable_transitions', $config['disable_transitions']);
        $container->setParameter('nowo_cookie_consent.disable_page_interaction', $config['disable_page_interaction']);
        $container->setParameter('nowo_cookie_consent.two_step_modal', $config['two_step_modal']);
        $container->setParameter('nowo_cookie_consent.open_preferences_modal', $config['open_preferences_modal']);
        $container->setParameter('nowo_cookie_consent.manage_iframe_placeholders', $config['manage_iframe_placeholders']);
        $container->setParameter('nowo_cookie_consent.granular_cookie_selection', $config['granular_cookie_selection']);
        $container->setParameter('nowo_cookie_consent.preferences_bubble_enabled', $config['preferences_bubble_enabled']);
        $container->setParameter('nowo_cookie_consent.preferences_bubble_position', $config['preferences_bubble_position']);
        $container->setParameter('nowo_cookie_consent.preferences_bubble_border_color', $config['preferences_bubble_border_color']);
        $container->setParameter('nowo_cookie_consent.preferences_bubble_icon', $config['preferences_bubble_icon']);
        $container->setParameter('nowo_cookie_consent.preference_sections', $config['preference_sections']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if ($config['table_prefix'] !== '') {
            $container->setDefinition(
                TablePrefixListener::class,
                (new Definition(TablePrefixListener::class))
                    ->setArguments([$config['table_prefix']])
                    ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']),
            );
        }

        if (!$config['use_database_config']) {
            $container->removeDefinition(\Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber::class);
        } else {
            $container->register('nowo_cookie_consent.translation.loader.array', ArrayLoader::class)
                ->addTag('translation.loader', ['alias' => 'array']);
        }
    }

    /**
     * Returns the configuration alias used in config files.
     *
     * @return string The configuration root alias
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
