<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Nowo\CookieConsentBundle\Config\ConsentProfileView;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigReaderInterface;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostRelation\HostProfileLink;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\Kernel;

use function array_map;
use function gc_collect_cycles;
use function memory_get_usage;
use function sprintf;

/**
 * The private entity manager, the only mode on the application's default manager since 2.0, through a real kernel
 * (Doctrine on one SQLite database, the host entities mapped on the same default manager): the admin saves write the
 * bundle's rows through the private manager and never flush the application's pending changes; the private manager is
 * dropped at the request boundary (worker mode with the kernel kept alive: the browser never reboots it); the config
 * read port answers view objects. Also the 2.0 setting table (unset, `true`, `false`, `entity_manager` naming the
 * default manager) and the cross-manager rules of lesson W1-2.
 */
final class PrivateEntityManagerKernelTest extends TestCase
{
    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testAdminSavesNeverFlushTheApplicationsPendingChanges(): void
    {
        $configId = $this->boot();
        $host     = $this->hostManager();
        $private  = $this->privateManager();

        // An existing host row, then pending changes in the application's manager (its handler has not committed yet):
        // a changed field and a new row.
        $edited = new HostNote();
        $host->persist($edited);
        $host->flush();
        $edited->title = 'Changed, not flushed';
        $note          = new HostNote();
        $host->persist($note);

        $browser = $this->browser();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));
        $this->keepAlive();
        $browser->submit($crawler->filter('form[name="cookie_definition"]')->form(), [
            'cookie_definition[name]' => '_gid',
        ]);
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $this->keepAlive();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/settings/profile', $configId));
        $this->keepAlive();
        $nameField = (string) $crawler->filter('input[name$="[name]"]')->attr('name');
        $form      = $crawler->filter('input[name$="[name]"]')->closest('form')?->form();
        self::assertNotNull($form);
        $browser->submit($form, [$nameField => 'Saved privately']);
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $connection = $host->getConnection();
        self::assertSame(['_ga', '_gid'], $connection->fetchFirstColumn('SELECT name FROM dashboard_cookie_definition ORDER BY name'));
        self::assertSame('Saved privately', $connection->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$configId]));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'), 'the application\'s pending insert was not flushed by the bundle');
        self::assertNull($connection->fetchOne('SELECT title FROM host_note WHERE id = ?', [$edited->id]), 'nor its changed field');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note), 'and it is still the application\'s to commit');
        self::assertTrue($host->contains($edited), 'the bundle never clears or detaches the application\'s manager');
        self::assertSame('Changed, not flushed', $edited->title);
        self::assertFalse($private->isCreated(), 'the private manager was dropped at the end of the request (kernel.terminate)');

        // The application commits its own work whenever it decides to.
        $host->flush();
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'));
        self::assertSame('Changed, not flushed', $connection->fetchOne('SELECT title FROM host_note WHERE id = ?', [$edited->id]));
    }

    public function testRepositoriesAndReadPortUseThePrivateManager(): void
    {
        $configId = $this->boot();
        $host     = $this->hostManager();
        $private  = $this->privateManager();
        $host->clear();

        $repository = $this->service(CookieConsentConfigRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        $config = $repository->findDefaultEnabled();
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        self::assertTrue($private->contains($config), 'loaded by the private manager');
        self::assertFalse($host->contains($config), 'never by the application\'s manager');

        $reader = $this->service(CookieConsentConfigReaderInterface::class);
        self::assertInstanceOf(CookieConsentConfigReaderInterface::class, $reader);
        self::assertSame($configId, $reader->defaultProfileId());
        self::assertSame('Public site', $reader->defaultProfile()?->name);
        self::assertSame([$configId], array_map(static fn (ConsentProfileView $view): ?int => $view->id, $reader->enabledProfiles()));

        // A host save through the repository with the private manager (the documented host-code path).
        $config->setName('Host code');
        $note = new HostNote();
        $host->persist($note);
        $repository->saveSettings($config, $private);

        self::assertSame('Host code', $host->getConnection()->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$configId]));
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'));
    }

    /**
     * FrankenPHP worker mode with the kernel kept alive (no services resetter): 600 consent submissions through
     * `http_kernel`. Every request ends with the private manager dropped, the application's manager never holds a
     * bundle row, an edit made by another worker is read by the next unit, and memory stays flat after warm-up.
     */
    public function testAKeptAliveKernelDropsThePrivateManagerAtEveryRequestWithFlatMemory(): void
    {
        $configId = $this->boot(null, ['use_logger' => true, 'csrf_protection' => false, 'categories' => ['analytics']]);
        $host     = $this->hostManager();
        $host->clear();
        $private = $this->privateManager();
        $reader  = $this->service(CookieConsentConfigReaderInterface::class);
        self::assertInstanceOf(CookieConsentConfigReaderInterface::class, $reader);
        $http = $this->service('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);

        $baseline = 0;
        for ($i = 0; $i < 600; ++$i) {
            if ($i % 100 === 0) {
                // Another worker renames the profile between two units.
                $host->getConnection()->executeStatement('UPDATE dashboard_cookie_config SET name = ? WHERE id = ?', ['Name ' . $i, $configId]);
            }

            $request  = Request::create('/cookie_consent', 'POST', ['cookie_consent' => ['use_all_cookies' => '']], [], [], ['REMOTE_ADDR' => '198.51.100.10']);
            $response = $http->handle($request);
            $http->terminate($request, $response);

            self::assertLessThan(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), 'request #' . $i);
            self::assertFalse($private->isCreated(), 'dropped at the end of request #' . $i);
            self::assertSame(0, $host->getUnitOfWork()->size(), 'the application\'s manager never holds a bundle row');
            self::assertSame('Name ' . ($i - $i % 100), $reader->defaultProfile()?->name, 'the next unit reads the other worker\'s edit');
            $private->reset(); // the read above is a unit of its own (as a console command or a message would be)

            if ($i === 100) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $baseline, 'Memory must stay flat after warm-up.');
    }

    /**
     * W1-2: a profile the application's manager holds (loaded through it), or the application's manager itself, never
     * reaches a bundle save: the save fails before anything is written, and the application's manager is untouched.
     */
    public function testASaveWithTheApplicationsManagerOrItsEntityFailsAndWritesNothing(): void
    {
        $configId   = $this->boot();
        $host       = $this->hostManager();
        $private    = $this->privateManager();
        $repository = $this->service(CookieConsentConfigRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        $host->clear();

        $hostHeld = $host->find(CookieConsentConfig::class, $configId);
        self::assertInstanceOf(CookieConsentConfig::class, $hostHeld);
        $hostHeld->setName('Through the application');
        $note = new HostNote();
        $host->persist($note);

        foreach ([[$hostHeld, $host], [$hostHeld, $private]] as [$config, $manager]) {
            try {
                $repository->saveSettings($config, $manager);
                self::fail('A save outside the bundle\'s manager must fail.');
            } catch (LogicException $e) {
                self::assertStringContainsString('saveSettings() needs the bundle\'s entity manager and an entity it holds', $e->getMessage());
                self::assertStringContainsString('Nothing was written', $e->getMessage());
            }
        }

        $connection = $host->getConnection();
        self::assertSame('Public site', $connection->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$configId]));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'), 'the application\'s manager was not flushed');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note));
        self::assertSame(1, (int) $connection->fetchOne('SELECT row_version FROM dashboard_cookie_config WHERE id = ?', [$configId]), 'no version advanced');

        // Loaded through the bundle's repository and saved with the bundle's manager: written, once.
        $own = $repository->find($configId);
        self::assertInstanceOf(CookieConsentConfig::class, $own);
        $repository->saveSettings($own->setName('Through the bundle'), $private);
        self::assertSame('Through the bundle', $connection->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$configId]));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_config'), 'no duplicate row');
    }

    /**
     * ZAO-4: an entity the bundle's manager holds, passed with the application's manager, fails too (for both saves):
     * the application's pending insert is not flushed and nothing of the bundle is written.
     */
    public function testABundleHeldEntityWithTheApplicationsManagerFailsForBothSaves(): void
    {
        $configId    = $this->boot();
        $host        = $this->hostManager();
        $configs     = $this->service(CookieConsentConfigRepository::class);
        $definitions = $this->service(CookieDefinitionRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $configs);
        self::assertInstanceOf(CookieDefinitionRepository::class, $definitions);
        $host->clear();

        $note = new HostNote();
        $host->persist($note);

        $config = $configs->find($configId);
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        $definition = $definitions->findOneBy(['name' => '_ga']);
        self::assertInstanceOf(CookieDefinition::class, $definition);
        self::assertTrue($this->privateManager()->contains($config));
        $config->setName('Through the application');
        $definition->setDuration('forever');

        foreach ([
            'saveSettings' => static fn () => $configs->saveSettings($config, $host),
            'saveEdit'     => static fn () => $definitions->saveEdit($definition, $host, 1),
        ] as $method => $save) {
            try {
                $save();
                self::fail($method . ' with the application\'s manager must fail.');
            } catch (LogicException $e) {
                self::assertStringContainsString($method . '() needs the bundle\'s entity manager', $e->getMessage());
            }
        }

        $connection = $host->getConnection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'), 'the application\'s pending insert stays unwritten');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note));
        self::assertSame('Public site', $connection->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$configId]));
        self::assertSame('', $connection->fetchOne('SELECT duration FROM dashboard_cookie_definition WHERE name = ?', ['_ga']));
        self::assertSame(1, (int) $connection->fetchOne('SELECT row_version FROM dashboard_cookie_definition WHERE name = ?', ['_ga']), 'no version advanced');
    }

    /**
     * W1-2: a host entity related to a bundle entity is written by the application's manager through
     * `getReference()`; the bundle's later saves do not touch it.
     */
    public function testAHostEntityReferencingABundleEntityIsWrittenByTheApplication(): void
    {
        $configId = $this->boot();
        $host     = $this->hostManager();
        $host->clear();

        $link          = new HostProfileLink();
        $link->profile = $host->getReference(CookieConsentConfig::class, $configId);
        $host->persist($link);
        $host->flush();

        self::assertSame($configId, (int) $host->getConnection()->fetchOne('SELECT profile_id FROM host_profile_link WHERE id = ?', [$link->id]));

        $repository = $this->service(CookieConsentConfigRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        $own = $repository->find($configId);
        self::assertInstanceOf(CookieConsentConfig::class, $own);
        self::assertNotSame($link->profile, $own, 'two managers, two instances: compare by id');
        $repository->saveSettings($own->setName('Renamed'), $this->privateManager());
        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_profile_link'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function privateSettings(): iterable
    {
        yield 'unset' => [[]];
        yield 'true (deprecated no-op)' => [['private_entity_manager' => true]];
        yield 'entity_manager naming the default manager' => [['entity_manager' => 'default']];
    }

    /**
     * The 2.0 setting table on the default manager: the bundle's repositories always answer entities of the private
     * manager, never of the application's.
     *
     * @param array<string, mixed> $doctrine
     */
    #[DataProvider('privateSettings')]
    public function testEverySettingOnTheDefaultManagerUsesThePrivateManager(array $doctrine): void
    {
        $this->boot(null, ['doctrine' => $doctrine]);
        $host = $this->hostManager();
        $host->clear();

        $repository = $this->service(CookieConsentConfigRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        $config = $repository->findDefaultEnabled();
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        self::assertTrue($this->privateManager()->contains($config));
        self::assertFalse($host->contains($config));
    }

    public function testFalseFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"nowo_cookie_consent.doctrine.private_entity_manager: false" was removed in 2.0');

        $this->boot(false);
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    private function boot(?bool $private = null, array $bundleConfig = []): int
    {
        $this->kernel = new CookieConsentTestKernel(false, $bundleConfig + [
            'use_database_config' => true,
            'web_ui'              => ['enabled' => true],
            'security'            => ['allow_unauthenticated' => true],
            'doctrine'            => $private === null ? [] : ['private_entity_manager' => $private],
        ], doctrineConfig: [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['mappings' => [
                'Host' => [
                    'type'      => 'attribute',
                    'is_bundle' => false,
                    'dir'       => __DIR__ . '/HostEntity',
                    'prefix'    => 'Nowo\\CookieConsentBundle\\Tests\\Integration\\Kernel\\HostEntity',
                ],
                // W1-2: a host entity with a ManyToOne to a bundle entity (same default manager).
                'HostRelation' => [
                    'type'      => 'attribute',
                    'is_bundle' => false,
                    'dir'       => __DIR__ . '/HostRelation',
                    'prefix'    => 'Nowo\\CookieConsentBundle\\Tests\\Integration\\Kernel\\HostRelation',
                ],
            ]],
        ], publicIds: [CookieConsentConfigReaderInterface::class]);
        $this->kernel->boot();

        $em = $this->hostManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $config = (new CookieConsentConfig())->setName('Public site')->setEnabled(true)->setDefault(true);
        $em->persist($config);
        $em->persist((new CookieDefinition())->setName('_ga')->setCategory('analytics')->setConfig($config));
        $em->flush();

        return (int) $config->getId();
    }

    private function browser(): KernelBrowser
    {
        self::assertNotNull($this->kernel);
        $browser = new KernelBrowser($this->kernel);
        $browser->disableReboot();

        return $browser;
    }

    /**
     * FrankenPHP worker mode with the kernel reset off: the next request does not run `services_resetter` (which
     * `Kernel::boot()` runs between two requests otherwise, clearing the application's managers).
     */
    private function keepAlive(): void
    {
        self::assertNotNull($this->kernel);
        (new ReflectionProperty(Kernel::class, 'resetServices'))->setValue($this->kernel, false);
    }

    private function container(): ContainerInterface
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function service(string $id): object
    {
        return $this->container()->get($id);
    }

    private function hostManager(): EntityManagerInterface
    {
        $em = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function privateManager(): PrivateEntityManager
    {
        $em = $this->service(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE);
        self::assertInstanceOf(PrivateEntityManager::class, $em);

        return $em;
    }
}
