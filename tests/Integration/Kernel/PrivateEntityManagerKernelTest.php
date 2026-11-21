<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Config\ConsentProfileView;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigReaderInterface;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
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
 * 1.34 `doctrine.private_entity_manager: true` through a real kernel (Doctrine on one SQLite database, the host entity
 * mapped on the same default manager): the admin saves write the bundle's rows through the private manager and never
 * flush the application's pending changes; the private manager is dropped at the request boundary (worker mode with
 * the kernel kept alive: the browser never reboots it); the config read port answers view objects.
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
        $configId = $this->boot(true);
        $host     = $this->hostManager();
        $private  = $this->privateManager();

        // The application left a change pending in its own manager (its handler has not committed yet).
        $note = new HostNote();
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
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'), 'the application\'s pending insert was not flushed by the bundle');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note), 'and it is still the application\'s to commit');
        self::assertFalse($private->isCreated(), 'the private manager was dropped at the end of the request (kernel.terminate)');

        // The application commits its own work whenever it decides to.
        $host->flush();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'));
    }

    public function testRepositoriesAndReadPortUseThePrivateManager(): void
    {
        $configId = $this->boot(true);
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
        $configId = $this->boot(true, ['use_logger' => true, 'csrf_protection' => false, 'categories' => ['analytics']]);
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

    public function testDefaultModeKeepsTheApplicationsManager(): void
    {
        $this->boot(false);
        $host = $this->hostManager();
        $host->clear();

        $repository = $this->service(CookieConsentConfigRepository::class);
        self::assertInstanceOf(CookieConsentConfigRepository::class, $repository);
        $config = $repository->findDefaultEnabled();
        self::assertInstanceOf(CookieConsentConfig::class, $config);
        self::assertTrue($host->contains($config), '1.x behaviour unchanged');
        self::assertFalse($this->container()->has(NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_SERVICE));
    }

    /**
     * @param array<string, mixed> $bundleConfig
     */
    private function boot(bool $private, array $bundleConfig = []): int
    {
        $this->kernel = new CookieConsentTestKernel(false, $bundleConfig + [
            'use_database_config' => true,
            'web_ui'              => ['enabled' => true],
            'security'            => ['allow_unauthenticated' => true],
            'doctrine'            => ['private_entity_manager' => $private],
        ], doctrineConfig: [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['mappings' => ['Host' => [
                'type'      => 'attribute',
                'is_bundle' => false,
                'dir'       => __DIR__ . '/HostEntity',
                'prefix'    => 'Nowo\\CookieConsentBundle\\Tests\\Integration\\Kernel\\HostEntity',
            ]]],
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
