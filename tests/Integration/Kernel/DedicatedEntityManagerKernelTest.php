<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentSchemaReadySubscriber;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * `doctrine.entity_manager` through a real kernel with two entity managers on two SQLite databases: the bundle
 * entities are mapped on the dedicated manager only, consents and the schema probe use its connection, and the
 * logger flushes no manager at all.
 */
final class DedicatedEntityManagerKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testEntitiesAndConsentLogLiveOnTheDedicatedManager(): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_logger' => true,
            'categories' => ['analytics'],
            'doctrine'   => ['entity_manager' => 'cookie_consent'],
            'security'   => ['allow_unauthenticated' => true],
        ], doctrineConfig: [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'consent' => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default'        => ['connection' => 'default'],
                    'cookie_consent' => ['connection' => 'consent'],
                ],
            ],
        ]);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $consentManager = $registry->getManager('cookie_consent');
        $hostManager    = $registry->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $consentManager);
        self::assertInstanceOf(EntityManagerInterface::class, $hostManager);

        self::assertSame($consentManager, $registry->getManagerForClass(CookieConsentLog::class));
        self::assertTrue($hostManager->getMetadataFactory()->isTransient(CookieConsentLog::class), 'not mapped on the host manager');

        (new SchemaTool($consentManager))->createSchema($consentManager->getMetadataFactory()->getAllMetadata());

        $subscriber = $container->get(CookieConsentSchemaReadySubscriber::class);
        self::assertInstanceOf(CookieConsentSchemaReadySubscriber::class, $subscriber);
        $connection = (fn (): object => $this->connection)->call($subscriber);
        self::assertSame($consentManager->getConnection(), $connection, 'schema probe runs on the dedicated connection');

        $consentManager->persist((new CookieConsentConfig())->setName('Pending in the bundle manager'));

        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));

        $logger = $container->get(CookieLoggerInterface::class);
        self::assertInstanceOf(CookieLoggerInterface::class, $logger);
        $logger->log(['analytics' => true], 'kernel-key');

        self::assertSame(
            ['kernel-key'],
            $consentManager->getConnection()->fetchFirstColumn('SELECT cookie_consent_key FROM dashboard_cookie_log'),
        );
        self::assertSame(
            [],
            $consentManager->getConnection()->fetchFirstColumn('SELECT name FROM dashboard_cookie_config'),
            'the logger does not flush its own manager either',
        );
    }

    /**
     * KX-C-M1: the Flex default keeps `auto_mapping: true` on the default manager when a host adds a second one.
     * The bundle entities must still be mapped on the dedicated manager only, and the logger, the repositories and
     * the schema probe must all use it.
     */
    public function testDefaultManagerWithAutoMappingDoesNotMapTheBundleEntities(): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_logger'          => true,
            'use_database_config' => true,
            'categories'          => ['analytics'],
            'doctrine'            => ['entity_manager' => 'cookie_consent'],
            'security'            => ['allow_unauthenticated' => true],
        ], doctrineConfig: [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'consent' => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default'        => ['connection' => 'default', 'auto_mapping' => true],
                    'cookie_consent' => ['connection' => 'consent'],
                ],
            ],
        ]);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $consentManager = $registry->getManager('cookie_consent');
        $hostManager    = $registry->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $consentManager);
        self::assertInstanceOf(EntityManagerInterface::class, $hostManager);

        foreach ([CookieConsentLog::class, CookieConsentConfig::class] as $class) {
            self::assertTrue($hostManager->getMetadataFactory()->isTransient($class), $class . ' is not mapped on the auto-mapped default manager');
            self::assertSame($consentManager, $registry->getManagerForClass($class));
        }

        // Schema only on the dedicated database: any read or write on the default one would fail.
        (new SchemaTool($consentManager))->createSchema($consentManager->getMetadataFactory()->getAllMetadata());
        $consentManager->persist((new CookieConsentConfig())->setName('Site')->setEnabled(true)->setDefault(true));
        $consentManager->flush();
        $consentManager->clear();

        $repository = $container->get(CookieConsentConfigRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        self::assertSame('Site', $repository->findDefaultEnabled()?->getName());

        $subscriber = $container->get(CookieConsentSchemaReadySubscriber::class);
        self::assertInstanceOf(CookieConsentSchemaReadySubscriber::class, $subscriber);
        self::assertSame($consentManager->getConnection(), (fn (): object => $this->connection)->call($subscriber));

        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push(Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.10']));
        $logger = $container->get(CookieLoggerInterface::class);
        self::assertInstanceOf(CookieLoggerInterface::class, $logger);
        $logger->log(['analytics' => true], 'auto-mapped-key');

        self::assertSame(['auto-mapped-key'], $consentManager->getConnection()->fetchFirstColumn('SELECT cookie_consent_key FROM dashboard_cookie_log'));
        self::assertSame([], $hostManager->getConnection()->createSchemaManager()->listTableNames(), 'nothing on the host database');
    }

    /**
     * DoctrineBundle 3.4 regression: `auto_discover_entities` (on by default for a manager without `mappings` and
     * `auto_mapping`) gives the host manager's chain a default attribute driver, which claims every `#[ORM\Entity]`
     * class, so `doctrine`'s `getManagerForClass()` answered the host manager and host-facing code (EntityType,
     * UniqueEntity, MapEntity, `getRepository()`) queried its database. The explicit catch-all default driver
     * reproduces it on every DoctrineBundle version.
     */
    #[DataProvider('hostManagersWithADefaultDriver')]
    public function testDedicatedManagerWinsOverADefaultDriverOfTheHostManager(bool $explicitDefaultDriver): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_database_config' => true,
            'doctrine'            => ['entity_manager' => 'cookie_consent'],
            'security'            => ['allow_unauthenticated' => true],
        ], doctrineConfig: [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'consent' => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default'        => ['connection' => 'default'],
                    'cookie_consent' => ['connection' => 'consent'],
                ],
            ],
        ], hostDefaultDriver: $explicitDefaultDriver);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $registry = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        // Neither manager is initialized yet: DoctrineBundle 3.4 asks the metadata drivers.
        $consentManager = $registry->getManager('cookie_consent');
        foreach ([CookieConsentConfig::class, CookieConsentLog::class] as $class) {
            self::assertSame($consentManager, $registry->getManagerForClass($class), $class);
        }

        $hostManager = $registry->getManager('default');
        self::assertInstanceOf(EntityManagerInterface::class, $consentManager);
        self::assertInstanceOf(EntityManagerInterface::class, $hostManager);
        foreach ([CookieConsentConfig::class, CookieConsentLog::class] as $class) {
            self::assertTrue($hostManager->getMetadataFactory()->isTransient($class), $class . ' is not mapped on the host manager');
            self::assertSame($consentManager, $registry->getManagerForClass($class), $class);
        }

        // Schema only on the dedicated database: any read or write on the host one would fail ("no such table").
        (new SchemaTool($consentManager))->createSchema($consentManager->getMetadataFactory()->getAllMetadata());
        $writer = $registry->getManagerForClass(CookieConsentConfig::class);
        self::assertInstanceOf(EntityManagerInterface::class, $writer);
        $writer->persist((new CookieConsentConfig())->setName('Site')->setEnabled(true)->setDefault(true));
        $writer->flush();
        $writer->clear();

        $repository = $registry->getRepository(CookieConsentConfig::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        self::assertSame('Site', $repository->findDefaultEnabled()?->getName(), 'the repository reads the bundle database');
        self::assertSame(['Site'], $consentManager->getConnection()->fetchFirstColumn('SELECT name FROM dashboard_cookie_config'));
        self::assertSame([], $hostManager->getConnection()->createSchemaManager()->listTableNames(), 'nothing on the host database');

        if ($explicitDefaultDriver) {
            // The host manager keeps its own entities, and only them.
            self::assertSame([HostNote::class], array_map(static fn (ClassMetadata $metadata): string => $metadata->getName(), $hostManager->getMetadataFactory()->getAllMetadata()));
            self::assertSame($hostManager, $registry->getManagerForClass(HostNote::class));
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function hostManagersWithADefaultDriver(): iterable
    {
        yield 'host manager without mappings (DoctrineBundle 3.4 auto-discovery)' => [false];
        yield 'host manager with a catch-all default driver' => [true];
    }
}
