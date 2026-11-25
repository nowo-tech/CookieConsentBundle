<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Admin\AdminPageFactory;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigReader;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigReaderInterface;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Cookie\CookieCheckerInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\DependencyInjection\Configuration;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\DependencyInjection\TablePrefixListener;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Event\ConsentSavedEvent;
use Nowo\CookieConsentBundle\EventListener\CookieConsentConflictListener;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentConfigTranslationSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentPrivateManagerBoundarySubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\ConfigurableCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManagerMiddleware;
use Nowo\TransactionDoctrine\PrivateManager\PrivateManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class NowoCookieConsentExtensionTest extends TestCase
{
    public function testLoadsParametersWithoutTablePrefixListener(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([[]], $container);

        self::assertSame('', $container->getParameter('nowo_cookie_consent.table_prefix'));
        self::assertSame('en', $container->getParameter('nowo_cookie_consent.default_locale'));
        self::assertSame([], $container->getParameter('nowo_cookie_consent.skip_render_routes'));
        self::assertSame([], $container->getParameter('nowo_cookie_consent.render_routes'));
        self::assertSame(['en', 'es', 'it', 'fr', 'de', 'pt', 'nl', 'pl', 'ca'], $container->getParameter('nowo_cookie_consent.enabled_locales'));
        self::assertSame('bootstrap', $container->getParameter('nowo_cookie_consent.ui_theme'));
        self::assertFalse($container->hasDefinition(TablePrefixListener::class));
    }

    public function testRegistersTablePrefixListenerWhenDoctrinePrefixConfigured(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['doctrine' => ['table_prefix' => 'demo_']]], $container);

        self::assertSame('demo_', $container->getParameter('nowo_cookie_consent.table_prefix'));
        self::assertSame('demo_', $container->getParameter('nowo_cookie_consent.doctrine.table_prefix'));
        self::assertTrue($container->hasDefinition(TablePrefixListener::class));
    }

    public function testEntityManagerDefaultsToTheDefaultManager(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[]], $container);

        self::assertNull($container->getParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER));
        self::assertArrayNotHasKey(
            EntityManagerInterface::class . ' $entityManager',
            $container->getDefinition(CookieDefinitionAdminController::class)->getBindings(),
        );
        self::assertNotInstanceOf(Reference::class, $container->getDefinition(CookieLogger::class)->getArguments()['$entityManager'] ?? null);
    }

    public function testEntityManagerOptionWiresTheBundleServicesToThatManager(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([['doctrine' => ['entity_manager' => ' cookie_consent ']]], $container);

        self::assertSame('cookie_consent', $container->getParameter(NowoCookieConsentExtension::ENTITY_MANAGER_PARAMETER));

        $registry = $container->getDefinition(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE);
        self::assertSame(CookieConsentManagerRegistry::class, $registry->getClass());
        self::assertSame('cookie_consent', $registry->getArgument(1));
        self::assertSame(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE, (string) $container->getDefinition(CookieLogger::class)->getArgument('$managerRegistry'));
        self::assertSame(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE, (string) $container->getDefinition(CookieConsentConfigRepository::class)->getArgument('$registry'));

        $logger = $container->getDefinition(CookieLogger::class)->getArgument('$entityManager');
        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame('doctrine.orm.cookie_consent_entity_manager', (string) $logger);

        $connection = $container->getDefinition(CookieConsentSchemaReadySubscriber::class)->getArgument('$connection');
        self::assertInstanceOf(Definition::class, $connection);
        $factory = $connection->getFactory();
        self::assertIsArray($factory);
        self::assertSame('doctrine.orm.cookie_consent_entity_manager', (string) $factory[0]);
        self::assertSame('getConnection', $factory[1]);

        foreach ([CookieDefinitionAdminController::class, CookieConsentConfigSettingsAdminController::class] as $controller) {
            $bindings = $container->getDefinition($controller)->getBindings();
            self::assertArrayHasKey(EntityManagerInterface::class . ' $entityManager', $bindings, $controller);
            self::assertSame('doctrine.orm.cookie_consent_entity_manager', (string) $bindings[EntityManagerInterface::class . ' $entityManager']->getValues()[0]);
        }
    }

    public function testWithoutDoctrineBundleThereIsNoPrivateEntityManager(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[]], $container);

        self::assertFalse($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertFalse($container->hasParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER));
        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_REGISTRY_SERVICE));
        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE));
        // Excluded from the resource scan (Symfony keeps an abstract `container.excluded` placeholder only).
        self::assertFalse($container->hasDefinition(CookieConsentPrivateManagerBoundarySubscriber::class) && !$container->getDefinition(CookieConsentPrivateManagerBoundarySubscriber::class)->hasTag('container.excluded'));
        self::assertSame('doctrine', (string) $container->getDefinition(CookieConsentConflictListener::class)->getArgument(3));
    }

    /**
     * 2.0: a dedicated doctrine.entity_manager with private_entity_manager unset keeps that manager exactly as in 1.x
     * (the private manager registered by load() is dropped by the compiler pass, which knows the default manager).
     */
    public function testUseDedicatedEntityManagerRestoresTheDedicatedWiring(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        (new NowoCookieConsentExtension())->load([['doctrine' => ['entity_manager' => 'cookie_consent']]], $container);
        self::assertTrue($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE));

        NowoCookieConsentExtension::useDedicatedEntityManager($container, 'cookie_consent');

        self::assertFalse($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        foreach ([NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE, NowoCookieConsentExtension::PRIVATE_REGISTRY_SERVICE, NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE, CookieConsentPrivateManagerBoundarySubscriber::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }
        foreach ([CookieConsentConfigRepository::class, CookieDefinitionRepository::class] as $repository) {
            self::assertSame(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE, (string) $container->getDefinition($repository)->getArgument('$registry'), $repository);
        }
        foreach ([CookieDefinitionAdminController::class, CookieConsentConfigSettingsAdminController::class] as $controller) {
            $bindings = $container->getDefinition($controller)->getBindings();
            self::assertSame('doctrine.orm.cookie_consent_entity_manager', (string) $bindings[EntityManagerInterface::class . ' $entityManager']->getValues()[0], $controller);
        }
        $listener = $container->getDefinition(CookieConsentConflictListener::class);
        self::assertSame(NowoCookieConsentExtension::MANAGER_REGISTRY_SERVICE, (string) $listener->getArgument(3));
        self::assertSame('cookie_consent', $listener->getArgument(4));
    }

    public function testUseDedicatedEntityManagerSkipsMissingServices(): void
    {
        $container = new ContainerBuilder();
        $container->register(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE);

        NowoCookieConsentExtension::useDedicatedEntityManager($container, 'cookie_consent');

        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
        self::assertFalse($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
    }

    public function testPrivateEntityManagerNeedsDoctrineBundle(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([['doctrine' => ['private_entity_manager' => true]]], $container);

        self::assertFalse($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertFalse($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
    }

    /**
     * @return iterable<string, array{0: bool|null, 1: string|null, 2: string}>
     */
    public static function privateSources(): iterable
    {
        yield 'default manager, unset (2.0 default)' => [null, null, ''];
        yield 'default manager, true (deprecated no-op)' => [true, null, ''];
        yield 'doctrine.entity_manager, unset (decided by the pass)' => [null, 'cookie_consent', 'cookie_consent'];
        yield 'doctrine.entity_manager, true' => [true, 'cookie_consent', 'cookie_consent'];
    }

    #[DataProvider('privateSources')]
    public function testPrivateEntityManagerWiresTheBundleServicesToThePrivateManager(?bool $setting, ?string $entityManager, string $source): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        (new NowoCookieConsentExtension())->load([['doctrine' => ['private_entity_manager' => $setting, 'entity_manager' => $entityManager]]], $container);

        self::assertTrue($container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_PARAMETER));
        self::assertSame($setting, $container->getParameter(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SETTING_PARAMETER));
        self::assertSame($source, $container->getParameter(NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER));
        $sourceArgument = '%' . NowoCookieConsentExtension::PRIVATE_SOURCE_MANAGER_PARAMETER . '%';

        $manager = $container->getDefinition(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE);
        self::assertSame(PrivateEntityManager::class, $manager->getClass());
        self::assertSame('doctrine', (string) $manager->getArgument(0));
        self::assertSame($sourceArgument, $manager->getArgument(1));
        self::assertSame(CookieConsentConfig::class, $manager->getArgument(2));
        self::assertSame('NowoCookieConsentBundle', $manager->getArgument(3));
        self::assertTrue($manager->hasTag(PrivateEntityManager::TAG));
        self::assertSame([['method' => 'reset']], $manager->getTag('kernel.reset'));

        $registry = $container->getDefinition(NowoCookieConsentExtension::PRIVATE_REGISTRY_SERVICE);
        self::assertSame(PrivateManagerRegistry::class, $registry->getClass());
        self::assertSame(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE, (string) $registry->getArgument(1));
        self::assertSame(['Nowo\\CookieConsentBundle\\Entity'], $registry->getArgument(2));
        self::assertSame($sourceArgument, $registry->getArgument(3));

        $middleware = $container->getDefinition(NowoCookieConsentExtension::PRIVATE_MIDDLEWARE_SERVICE);
        self::assertSame(PrivateEntityManagerMiddleware::class, $middleware->getClass());
        self::assertFalse($middleware->hasTag('doctrine.middleware'), 'tagged by the compiler pass, for the source connection only');

        foreach ([CookieConsentConfigRepository::class, CookieDefinitionRepository::class] as $repository) {
            self::assertSame(NowoCookieConsentExtension::PRIVATE_REGISTRY_SERVICE, (string) $container->getDefinition($repository)->getArgument('$registry'), $repository);
        }

        foreach ([CookieDefinitionAdminController::class, CookieConsentConfigSettingsAdminController::class] as $controller) {
            $bindings = $container->getDefinition($controller)->getBindings();
            self::assertSame(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE, (string) $bindings[EntityManagerInterface::class . ' $entityManager']->getValues()[0], $controller);
        }

        $listener = $container->getDefinition(CookieConsentConflictListener::class);
        self::assertSame(NowoCookieConsentExtension::PRIVATE_REGISTRY_SERVICE, (string) $listener->getArgument(3));
        self::assertSame($sourceArgument, $listener->getArgument(4));

        // The consent log and the GDPR ports keep the source manager (DBAL only, they never flush).
        $logger = $container->getDefinition(CookieLogger::class)->getArguments()['$managerRegistry'] ?? null;
        self::assertNotSame(NowoCookieConsentExtension::PRIVATE_REGISTRY_SERVICE, $logger instanceof Reference ? (string) $logger : null);

        $boundaries = $container->getDefinition(CookieConsentPrivateManagerBoundarySubscriber::class);
        self::assertTrue($boundaries->hasTag('kernel.event_subscriber'));
        $resettables = $boundaries->getArgument(0);
        self::assertInstanceOf(IteratorArgument::class, $resettables);
        $ids = [];
        foreach ($resettables->getValues() as $reference) {
            self::assertInstanceOf(Reference::class, $reference);
            self::assertSame(ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE, $reference->getInvalidBehavior());
            $ids[] = (string) $reference;
        }
        self::assertSame([
            NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE,
            CookieConsentConfigRepository::class,
            CookieConsentConfigResolver::class,
            CookieInventoryProvider::class,
        ], $ids);
    }

    public function testConfigReaderPortIsAliased(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[]], $container);

        self::assertSame(CookieConsentConfigReader::class, (string) $container->getAlias(CookieConsentConfigReaderInterface::class));
        self::assertTrue($container->hasDefinition(CookieConsentConfigReader::class));
    }

    public function testEntityManagerWiringSkipsRemovedServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        (new ReflectionMethod($extension, 'wireEntityManager'))->invoke($extension, $container, 'cookie_consent');

        self::assertFalse($container->hasDefinition(CookieLogger::class));
        self::assertFalse($container->hasDefinition(CookieDefinitionAdminController::class));
    }

    public function testDatabaseConfigRegistersArrayLoaderAndKeepsTranslationSubscriber(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['use_database_config' => true]], $container);

        self::assertTrue($container->hasDefinition('nowo_cookie_consent.translation.loader.array'));
        self::assertTrue($container->hasDefinition(CookieConsentConfigTranslationSubscriber::class));
    }

    public function testPrivateEntityManagerWiringSkipsRemovedServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        (new ReflectionMethod($extension, 'registerPrivateEntityManager'))->invoke($extension, $container, null);

        self::assertTrue($container->hasDefinition(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
        self::assertFalse($container->hasDefinition(CookieConsentConfigRepository::class));
        self::assertFalse($container->hasDefinition(CookieDefinitionAdminController::class));
    }

    public function testSchemaReadyCacheTtlParameter(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[]], $container);
        self::assertSame(60, $container->getParameter('nowo_cookie_consent.schema_ready_cache_ttl'));

        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([['schema_ready_cache_ttl' => 0]], $container);
        self::assertSame(0, $container->getParameter('nowo_cookie_consent.schema_ready_cache_ttl'));
    }

    public function testDatabaseConfigDisabledRemovesTranslationSubscriber(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([['use_database_config' => false]], $container);

        self::assertFalse($container->hasDefinition(CookieConsentConfigTranslationSubscriber::class));
    }

    public function testLoadsWebUiAndSecurityDefaults(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([[]], $container);

        self::assertTrue($container->getParameter('nowo_cookie_consent.web_ui.enabled'));
        self::assertNull($container->getParameter('nowo_cookie_consent.web_ui.layout_template'));
        self::assertSame(Configuration::PAGE_TEMPLATES, $container->getParameter('nowo_cookie_consent.web_ui.templates'));
        self::assertSame(20, $container->getParameter('nowo_cookie_consent.web_ui.list_page_size'));
        foreach (['nowo_cookie_consent.web_ui.path_prefix', 'nowo_cookie_consent.web_ui.row_actions_display', 'nowo_cookie_consent.web_ui.css_framework', 'nowo_cookie_consent.web_ui.icon_set', 'nowo_cookie_consent.doctrine.connection'] as $removed) {
            self::assertFalse($container->hasParameter($removed), $removed . ' (removed in 2.0)');
        }
        self::assertSame(['ROLE_ADMIN'], $container->getParameter('nowo_cookie_consent.security.access_roles'));
        self::assertFalse($container->getParameter('nowo_cookie_consent.security.allow_unauthenticated'));
        self::assertTrue($container->hasAlias(CookieConsentAccessCheckerInterface::class));
    }

    public function testUsesCustomAccessCheckerServiceId(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.custom_access_checker', new Definition(AllowAllCookieConsentAccessChecker::class));
        $extension = new NowoCookieConsentExtension();
        $extension->load([[
            'security' => [
                'access_checker' => 'app.custom_access_checker',
            ],
        ]], $container);

        self::assertTrue($container->hasAlias(CookieConsentAccessCheckerInterface::class));
        self::assertSame(
            'app.custom_access_checker',
            (string) $container->getAlias(CookieConsentAccessCheckerInterface::class),
        );
    }

    public function testAllowUnauthenticatedWithoutSecurityUsesAllowAllChecker(): void
    {
        $container = new ContainerBuilder();
        $extension = new NowoCookieConsentExtension();
        $extension->load([[
            'security' => [
                'allow_unauthenticated' => true,
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_cookie_consent.access_checker.allow_all'));
        self::assertSame(
            AllowAllCookieConsentAccessChecker::class,
            $container->getDefinition('nowo_cookie_consent.access_checker.allow_all')->getClass(),
        );
        self::assertSame(
            'nowo_cookie_consent.access_checker.allow_all',
            (string) $container->getAlias(CookieConsentAccessCheckerInterface::class),
        );
    }

    public function testDefaultAccessCheckerWiresAuthorizationCheckerWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('security.authorization_checker', new Definition());
        $extension = new NowoCookieConsentExtension();
        $extension->load([[]], $container);

        $definition = $container->getDefinition('nowo_cookie_consent.access_checker.default');
        self::assertSame(ConfigurableCookieConsentAccessChecker::class, $definition->getClass());
        self::assertArrayHasKey('$authorizationChecker', $definition->getArguments());
    }

    public function testCookieInterfacesAliasTheDefaultServices(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[]], $container);

        self::assertSame(CookieChecker::class, (string) $container->getAlias(CookieCheckerInterface::class));
        self::assertSame(CookieLogger::class, (string) $container->getAlias(CookieLoggerInterface::class));
        self::assertSame('nowo_cookie_consent.cookie_writer', (string) $container->getAlias(ConsentCookieWriterInterface::class));
        self::assertSame(CookieHandler::class, $container->getDefinition('nowo_cookie_consent.cookie_writer')->getClass());
        self::assertFalse($container->has('Nowo\\CookieConsentBundle\\Cookie\\CookieHandlerInterface'), 'removed in 2.0');
        self::assertFalse($container->hasDefinition(ConsentSavedEvent::class));
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_cookie_consent', (new NowoCookieConsentExtension())->getAlias());
    }

    public function testPrependConfiguresAssets(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());
        (new NowoCookieConsentExtension())->prepend($container);
        $configs = $container->getExtensionConfig('framework');
        self::assertSame('/bundles/nowocookieconsent', $configs[0]['assets']['packages']['nowo_cookie_consent']['base_path']);
    }

    public function testPrependSkipsWithoutFramework(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->prepend($container);
        self::assertFalse($container->hasExtension('framework'));
    }

    public function testLoadsPageTemplateOverrides(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->load([[
            'web_ui' => [
                'layout_template' => 'layouts/admin.html.twig',
                'templates'       => ['settings' => 'admin/settings.html.twig'],
            ],
        ]], $container);

        self::assertSame('layouts/admin.html.twig', $container->getParameter('nowo_cookie_consent.web_ui.layout_template'));
        self::assertSame(['settings' => 'admin/settings.html.twig'] + Configuration::PAGE_TEMPLATES, $container->getParameter('nowo_cookie_consent.web_ui.templates'));
        self::assertTrue($container->hasDefinition(AdminPageFactory::class));
        self::assertArrayNotHasKey('$formThemes', $container->getDefinition(AdminPageFactory::class)->getArguments(), '2.0: no web_ui.chrome form theme');
        self::assertArrayNotHasKey('$rowActionsDisplay', $container->getDefinition(AdminPageFactory::class)->getArguments(), '2.0: no web_ui.chrome row actions display');
        self::assertSame([], $container->getDefinition(CookieConsentAdminTwigExtension::class)->getArguments());
    }

    /**
     * 2.0: the bundle no longer seeds UiKit (the web_ui.css_framework / icon_set / chrome keys are removed).
     */
    public function testPrependNeverTouchesUiKit(): void
    {
        $container = $this->containerWithUiKit();
        $container->prependExtensionConfig('nowo_cookie_consent', ['web_ui' => ['layout_template' => 'layouts/admin.html.twig']]);

        (new NowoCookieConsentExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_ui_kit'));
    }

    private function containerWithUiKit(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        return $container;
    }

    public function testPrependSkipsUiKitWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_ui_kit'));
    }

    public function testPrependAlignsFormKitDefaultsWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        (new NowoCookieConsentExtension())->prepend($container);

        $foundCss     = false;
        $foundProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap') {
                $foundCss = true;
            }
            if (isset($cfg['profiles']['cookie_consent'])) {
                $foundProfile = true;
                // Text controls: UiKit input_full slot; the collection wrapper gets no input classes.
                self::assertSame('nowo-ui-input nowo-ui-input--full', $cfg['profiles']['cookie_consent']['defaults']['attr']['class']);
                self::assertSame('', $cfg['profiles']['cookie_consent']['field_types']['collection']['attr']['class']);
            }
        }
        self::assertTrue($foundCss);
        self::assertTrue($foundProfile);
    }

    public function testPrependDoesNotOverrideExplicitFormKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('nowo_form_kit', [
            'css_framework' => 'tailwind',
            'profiles'      => [
                'cookie_consent' => [
                    'alias' => 'cookie_consent',
                ],
            ],
        ]);

        (new NowoCookieConsentExtension())->prepend($container);

        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap') {
                self::fail('Must not prepend FormKit css_framework when host already set it.');
            }
            if (isset($cfg['profiles']['cookie_consent']['translation_domain'])) {
                self::fail('Must not prepend FormKit cookie_consent profile when host already set it.');
            }
        }
        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertSame('tailwind', $configs[0]['css_framework'] ?? null);
    }

    public function testPrependSkipsFormKitWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        (new NowoCookieConsentExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }
}
