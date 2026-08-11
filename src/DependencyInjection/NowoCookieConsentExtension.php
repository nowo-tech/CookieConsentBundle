<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use Nowo\CookieConsentBundle\Config\CookieInventoryNormalizer;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber;
use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\ConfigurableCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\Loader\ArrayLoader;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Loads bundle configuration and registers services in the container.
 */
class NowoCookieConsentExtension extends Extension implements PrependExtensionInterface
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

        $tablePrefix = trim((string) ($config['doctrine']['table_prefix'] ?? ''));
        if ($tablePrefix === '' && isset($config['table_prefix'])) {
            $tablePrefix = trim((string) $config['table_prefix']);
        }

        $container->setParameter('nowo_cookie_consent.doctrine.connection', $config['doctrine']['connection'] ?? 'default');
        $container->setParameter('nowo_cookie_consent.doctrine.table_prefix', $tablePrefix);
        $container->setParameter('nowo_cookie_consent.table_prefix', $tablePrefix);
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
        $container->setParameter('nowo_cookie_consent.skip_render_routes', $config['skip_render_routes']);
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

        $webUi = $config['web_ui'];
        $container->setParameter('nowo_cookie_consent.web_ui.enabled', $webUi['enabled']);
        $container->setParameter('nowo_cookie_consent.web_ui.path_prefix', $webUi['path_prefix']);
        $container->setParameter('nowo_cookie_consent.web_ui.layout_template', $webUi['layout_template']);
        $container->setParameter('nowo_cookie_consent.web_ui.css_framework', $webUi['css_framework']);
        $container->setParameter('nowo_cookie_consent.web_ui.icon_set', $webUi['icon_set']);
        $container->setParameter('nowo_cookie_consent.web_ui.list_page_size', $webUi['list_page_size']);

        $security = $config['security'];
        $container->setParameter('nowo_cookie_consent.security.access_roles', $security['access_roles']);
        $container->setParameter('nowo_cookie_consent.security.allow_unauthenticated', $security['allow_unauthenticated']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if ($container->hasDefinition(CookieConsentAdminTwigExtension::class)) {
            $container->getDefinition(CookieConsentAdminTwigExtension::class)
                ->setArgument('$layoutTemplate', $webUi['layout_template'])
                ->setArgument('$cssFramework', $webUi['css_framework'])
                ->setArgument('$iconSet', $webUi['icon_set']);
        }

        $this->registerAccessChecker($container, $security);

        if ($tablePrefix !== '') {
            $container->setDefinition(
                TablePrefixListener::class,
                (new Definition(TablePrefixListener::class))
                    ->setArguments([$tablePrefix])
                    ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']),
            );
        }

        if (!$config['use_database_config']) {
            $container->removeDefinition(CookieConsentConfigTranslationSubscriber::class);
        } else {
            $container->register('nowo_cookie_consent.translation.loader.array', ArrayLoader::class)
                ->addTag('translation.loader', ['alias' => 'array']);
        }
    }

    /**
     * @param array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        $accessCheckerId = $security['access_checker'] ?? null;
        if (is_string($accessCheckerId) && $accessCheckerId !== '') {
            $container->setAlias(CookieConsentAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $hasAuthorizationChecker = $container->hasDefinition('security.authorization_checker')
            || $container->hasAlias('security.authorization_checker');

        if ($security['allow_unauthenticated'] && !$hasAuthorizationChecker) {
            $accessCheckerId = 'nowo_cookie_consent.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllCookieConsentAccessChecker::class));
            $container->setAlias(CookieConsentAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $accessCheckerId = 'nowo_cookie_consent.access_checker.default';
        $definition      = new Definition(ConfigurableCookieConsentAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles']);
        if ($hasAuthorizationChecker) {
            $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        } else {
            // Placeholder until SecurityBundle registers the checker; SecurityPass fails compile if still missing.
            $definition->setAutowired(true);
        }
        $container->setDefinition($accessCheckerId, $definition);
        $container->setAlias(CookieConsentAccessCheckerInterface::class, $accessCheckerId);
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

    /**
     * Registers the bundle asset package before the FrameworkExtension processes assets,
     * and seeds UiKit defaults from web_ui when the host has not set nowo_ui_kit (REQ-UI-001-kit).
     */
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependFormKitDefaults($container);
        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/nowocookieconsent',
                        ],
                    ],
                ],
            ]);
        }

        $this->prependUiKitDefaults($container);
    }

    /**
     * When FormKit is installed, register the cookie_consent profile. Forms select it via #[FormKitConfig].
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasProfile      = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('cookie_consent', $profiles)) {
                $hostHasProfile = true;
            }
        }

        $seed = [];

        if (!$hostHasCssFramework) {
            $seed['css_framework'] = 'bootstrap';
        }

        if (!$hostHasProfile) {
            $seed['profiles'] = [
                'cookie_consent' => [
                    'alias'              => 'cookie_consent',
                    'translation_domain' => 'NowoCookieConsentBundle',
                    'defaults'           => [
                        'attr'     => ['class' => 'nowo-ui-input form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                    'field_types' => [
                        'checkbox' => [
                            'attr'     => ['class' => 'form-check-input'],
                            'row_attr' => ['class' => 'form-check mb-2'],
                        ],
                        'choice' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'entity' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'file' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                        'textarea' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

    /**
     * When UiKit is installed, seed nowo_ui_kit.css_framework / icon_set from web_ui
     * so kit macros resolve the same stack. Does not override keys the host already set.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet      = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        if ($hostHasCssFramework && $hostHasIconSet) {
            return;
        }

        $config   = $this->processConfiguration(new Configuration(), $container->getExtensionConfig(Configuration::ALIAS));
        $webUi    = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $defaults = [];

        if (!$hostHasCssFramework) {
            $fw                        = (string) ($webUi['css_framework'] ?? 'bootstrap5');
            $defaults['css_framework'] = $fw === 'bootstrap' ? 'bootstrap5' : $fw;
        }
        if (!$hostHasIconSet) {
            $defaults['icon_set'] = (string) ($webUi['icon_set'] ?? 'bootstrap-icons');
        }

        $container->prependExtensionConfig('nowo_ui_kit', $defaults);
    }
}
