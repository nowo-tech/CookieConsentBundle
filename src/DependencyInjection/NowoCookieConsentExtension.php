<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\DependencyInjection;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\AdminChrome;
use Nowo\CookieConsentBundle\Admin\AdminPageFactory;
use Nowo\CookieConsentBundle\Config\CookieInventoryNormalizer;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentManagerRegistry;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use Nowo\CookieConsentBundle\Locale\ConfiguredLocales;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindowProviderInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporter;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentLogsPersonalDataEraser;
use Nowo\CookieConsentBundle\PersonalData\UserKit\CookieConsentsPersonalDataExportProvider;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionTranslationRepository;
use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\ConfigurableCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface as UserKitPersonalDataEraserInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\Loader\ArrayLoader;

use function array_key_exists;
use function interface_exists;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Loads bundle configuration and registers services in the container.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class NowoCookieConsentExtension extends Extension implements PrependExtensionInterface
{
    /** Container parameter holding `doctrine.entity_manager` (null: the default entity manager). */
    public const ENTITY_MANAGER_PARAMETER = 'nowo_cookie_consent.doctrine.entity_manager';

    /** Defined when DoctrineBundle is registered (1.28): Symfony's mapping pass is skipped otherwise. */
    public const MAPPING_ENABLED_PARAMETER = 'nowo_cookie_consent.doctrine.mapping_enabled';

    /** Tag of the {@see CookieConsentIpWindowProviderInterface} services (autoconfigured, 1.29). */
    public const IP_WINDOW_PROVIDER_TAG = 'nowo_cookie_consent.personal_data.ip_window_provider';

    /** Registry decorator wired into the bundle's repositories and logger when `doctrine.entity_manager` is set. */
    public const MANAGER_REGISTRY_SERVICE = 'nowo_cookie_consent.manager_registry';

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
        $entityManagerName = $config['doctrine']['entity_manager'] ?? null;
        $entityManagerName = is_string($entityManagerName) && trim($entityManagerName) !== '' ? trim($entityManagerName) : null;
        $container->setParameter(self::ENTITY_MANAGER_PARAMETER, $entityManagerName);
        if ($this->hasDoctrineBundle($container)) {
            // 1.28: the entities are mapped only when DoctrineBundle is registered (standard hosts: as before).
            $container->setParameter(self::MAPPING_ENABLED_PARAMETER, true);
        }
        $container->setParameter('nowo_cookie_consent.table_prefix', $tablePrefix);
        $container->setParameter('nowo_cookie_consent.categories', $config['categories']);
        $container->setParameter('nowo_cookie_consent.use_logger', $config['use_logger']);
        $container->setParameter('nowo_cookie_consent.use_database_config', $config['use_database_config']);
        $container->setParameter('nowo_cookie_consent.schema_ready_cache_ttl', $config['schema_ready_cache_ttl']);
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
        $container->setParameter('nowo_cookie_consent.privacy_route', $config['privacy_route'] ?? null);
        $container->setParameter('nowo_cookie_consent.skip_render_routes', $config['skip_render_routes']);
        $container->setParameter('nowo_cookie_consent.render_routes', $config['render_routes']);
        $container->setParameter('nowo_cookie_consent.route_targeting_mode', $config['route_targeting_mode']);
        $container->setParameter('nowo_cookie_consent.target_routes', $config['target_routes']);
        $container->setParameter('nowo_cookie_consent.default_locale', $config['default_locale']);
        // enabled_locales may be a parameter (%app.locales%, %kernel.enabled_locales%): the kernel resolves it before
        // load(), a direct load() caller may not. An env var (%env(json:APP_LOCALES)%) stays a placeholder until runtime.
        $enabledLocales = $container->getParameterBag()->resolveValue($config['enabled_locales']);
        $usedEnvs       = [];
        $container->resolveEnvPlaceholders($enabledLocales, null, $usedEnvs);
        $runtimeLocales = $usedEnvs !== [];
        $container->setParameter('nowo_cookie_consent.enabled_locales', $runtimeLocales ? $enabledLocales : ConfiguredLocales::normalize($enabledLocales));
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
        $chrome = AdminChrome::resolve($webUi['chrome'] ?? []);
        $container->setParameter('nowo_cookie_consent.web_ui.layout_template', $webUi['layout_template']);
        $container->setParameter('nowo_cookie_consent.web_ui.templates', $webUi['templates'] + Configuration::PAGE_TEMPLATES);
        $container->setParameter('nowo_cookie_consent.web_ui.row_actions_display', $chrome['row_actions_display']);
        $container->setParameter('nowo_cookie_consent.web_ui.css_framework', $webUi['css_framework']);
        $container->setParameter('nowo_cookie_consent.web_ui.icon_set', $webUi['icon_set']);
        $container->setParameter('nowo_cookie_consent.web_ui.list_page_size', $webUi['list_page_size']);

        $security = $config['security'];
        $container->setParameter('nowo_cookie_consent.security.access_roles', $security['access_roles']);
        $container->setParameter('nowo_cookie_consent.security.allow_unauthenticated', $security['allow_unauthenticated']);

        $personalData = $config['personal_data'];
        $container->setParameter('nowo_cookie_consent.personal_data.enabled', $personalData['enabled']);
        $container->setParameter('nowo_cookie_consent.personal_data.ip_window_margin', $personalData['ip_window_margin']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->registerPersonalData($container, $personalData);

        if ($container->hasDefinition(CookieConsentAdminTwigExtension::class)) {
            // Deprecated globals (1.x); the admin pages read the UiKit layout / theme instead.
            $container->getDefinition(CookieConsentAdminTwigExtension::class)
                ->setArgument('$layoutTemplate', $webUi['layout_template'] ?? CookieConsentAdminTwigExtension::UI_KIT_ADMIN_LAYOUT)
                ->setArgument('$cssFramework', $webUi['css_framework'] ?? 'bootstrap5')
                ->setArgument('$iconSet', $webUi['icon_set'] ?? 'bootstrap-icons')
                ->setArgument('$chrome', $chrome);
        }

        if ($container->hasDefinition(AdminPageFactory::class)) {
            // Deprecated web_ui.chrome.templates.form_theme: applied through FormPage / SettingsSection::$formThemes.
            $formTheme = $chrome['templates']['form_theme'];
            $container->getDefinition(AdminPageFactory::class)
                ->setArgument('$formThemes', $formTheme !== null ? [$formTheme] : []);
        }

        if ($runtimeLocales) {
            // Env var locales are only known at runtime: normalized by a factory when the resolver is created.
            $container->getDefinition(LocaleResolver::class)->setArgument('$enabledLocales', (new Definition('array'))
                ->setFactory([ConfiguredLocales::class, 'normalize'])
                ->setArguments(['%nowo_cookie_consent.enabled_locales%']));
        }

        $this->registerAccessChecker($container, $security);
        $this->wireEntityManager($container, $entityManagerName);

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
     * GDPR ports (1.29): autoconfigured IP window providers, the optional custom subject resolver, and the UserKit
     * eraser / export provider when UserKit is installed, the bundle entities are mapped and `personal_data.enabled`.
     *
     * @param array{enabled: bool, ip_window_margin: string, subject_resolver: ?string} $personalData
     */
    private function registerPersonalData(ContainerBuilder $container, array $personalData): void
    {
        $container->registerForAutoconfiguration(CookieConsentIpWindowProviderInterface::class)
            ->addTag(self::IP_WINDOW_PROVIDER_TAG);

        $resolver = $personalData['subject_resolver'];
        if (is_string($resolver) && trim($resolver) !== '') {
            $container->setAlias(CookieConsentPersonalDataSubjectResolverInterface::class, trim($resolver));
        }

        if (!$personalData['enabled'] || !$this->hasDoctrineBundle($container) || !interface_exists(UserKitPersonalDataEraserInterface::class)) {
            return;
        }

        $container->register(CookieConsentLogsPersonalDataEraser::class, CookieConsentLogsPersonalDataEraser::class)
            ->setAutowired(true)
            ->addTag('nowo_user_kit.personal_data.eraser', ['priority' => CookieConsentLogsPersonalDataEraser::PRIORITY]);
        $container->register(CookieConsentsPersonalDataExportProvider::class, CookieConsentsPersonalDataExportProvider::class)
            ->setAutowired(true)
            ->addTag('nowo_user_kit.personal_data.export_provider', ['priority' => CookieConsentsPersonalDataExportProvider::PRIORITY]);
    }

    /**
     * DoctrineBundle is registered (`kernel.bundles`; load() runs in a container holding only this extension).
     */
    private function hasDoctrineBundle(ContainerBuilder $container): bool
    {
        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];

        return (is_array($bundles) && isset($bundles['DoctrineBundle'])) || $container->hasExtension('doctrine');
    }

    /**
     * Points the services that use the bundle's entity manager (or its connection) at `doctrine.entity_manager`.
     *
     * Repositories resolve their manager by entity class already; this covers the injected ones.
     */
    private function wireEntityManager(ContainerBuilder $container, ?string $entityManagerName): void
    {
        if ($entityManagerName === null) {
            return;
        }

        $entityManager = new Reference(sprintf('doctrine.orm.%s_entity_manager', $entityManagerName));

        // Repositories and the logger resolve the bundle entities to that manager explicitly (KX-C-M1).
        $registry = new Reference(self::MANAGER_REGISTRY_SERVICE);
        $container->register(self::MANAGER_REGISTRY_SERVICE, CookieConsentManagerRegistry::class)
            ->setArguments([new Reference('doctrine'), $entityManagerName]);

        foreach ([
            CookieConsentConfigRepository::class,
            CookieConsentConfigTranslationRepository::class,
            CookieDefinitionRepository::class,
            CookieDefinitionTranslationRepository::class,
        ] as $repository) {
            if ($container->hasDefinition($repository)) {
                $container->getDefinition($repository)->setArgument('$registry', $registry);
            }
        }

        if ($container->hasDefinition(CookieLogger::class)) {
            $container->getDefinition(CookieLogger::class)
                ->setArgument('$entityManager', $entityManager)
                ->setArgument('$managerRegistry', $registry);
        }

        foreach ([CookieConsentPersonalDataEraser::class, CookieConsentPersonalDataExporter::class] as $port) {
            if ($container->hasDefinition($port)) {
                $container->getDefinition($port)->setArgument('$managerRegistry', $registry);
            }
        }

        if ($container->hasDefinition(CookieConsentSchemaReadySubscriber::class)) {
            $container->getDefinition(CookieConsentSchemaReadySubscriber::class)->setArgument(
                '$connection',
                (new Definition(Connection::class))->setFactory([$entityManager, 'getConnection']),
            );
        }

        foreach ([CookieConsentConfigSettingsAdminController::class, CookieDefinitionAdminController::class] as $controller) {
            if (!$container->hasDefinition($controller)) {
                continue;
            }

            $definition = $container->getDefinition($controller);
            $definition->setBindings($definition->getBindings() + [
                EntityManagerInterface::class . ' $entityManager' => $entityManager,
            ]);
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
     * Seeds UiKit from the bundle config when UiKit is installed: the deprecated `web_ui.css_framework` /
     * `web_ui.icon_set` overrides (when the host has not set nowo_ui_kit's own keys) and the deprecated
     * `web_ui.chrome` map, bridged into `nowo_ui_kit.theme.bundles.NowoCookieConsentBundle` (REQ-UI-003). Prepended,
     * so a host's own nowo_ui_kit config still wins.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        // Only the web_ui node is processed here: prepend() sees the raw host config, where other keys may still be
        // "%parameter%" / env placeholders (e.g. enabled_locales: '%kernel.enabled_locales%', defined by
        // FrameworkExtension::load() after prepend).
        $webUiConfigs = [];
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $cfg) {
            if (is_array($cfg['web_ui'] ?? null)) {
                $webUiConfigs[] = ['web_ui' => $cfg['web_ui']];
            }
        }
        $config = $this->processConfiguration(new Configuration(), $webUiConfigs);
        $webUi  = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];

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

        $defaults = [];
        $fw       = $webUi['css_framework'] ?? null;
        if (!$hostHasCssFramework && is_string($fw)) {
            $defaults['css_framework'] = $fw === 'bootstrap' ? 'bootstrap5' : $fw;
        }
        $iconSet = $webUi['icon_set'] ?? null;
        if (!$hostHasIconSet && is_string($iconSet)) {
            $defaults['icon_set'] = $iconSet;
        }

        $chrome = is_array($webUi['chrome'] ?? null) ? $webUi['chrome'] : [];
        $theme  = AdminChrome::toUiKitTheme($chrome);
        if ($theme !== []) {
            $defaults['theme'] = ['bundles' => [AdminChrome::BUNDLE => $theme]];
        }

        if ($defaults !== []) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
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
                    // The form types set every help text they have explicitly: no "{form}.{field}.help"
                    // convention keys (the bundle ships none, so they rendered raw).
                    'auto_help' => false,
                    // The host form theme (e.g. bootstrap_5_layout) adds form-control / form-select /
                    // form-check-input, the .form-check wrapper and the row spacing itself: repeating them here
                    // doubled the classes and nested a second .form-check around checkboxes. No framework
                    // utility classes here (REQ-UI-003): rows get their spacing from the form theme. Text controls
                    // use the UiKit `input_full` slot (`nowo-ui-input nowo-ui-input--full`): the form grid cells and
                    // the translations table size them, so every input of a row lines up.
                    'defaults' => [
                        'attr' => ['class' => ThemeSlots::semantic('input_full')],
                    ],
                    'field_types' => [
                        // The collection wrapper is not a control: no input classes (they capped it at 20rem).
                        'collection' => [
                            'attr' => ['class' => ''],
                        ],
                        'checkbox' => [
                            'attr' => ['class' => ''],
                        ],
                        'choice' => [
                            'attr' => ['class' => ''],
                        ],
                        'entity' => [
                            'attr' => ['class' => ''],
                        ],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }
}
