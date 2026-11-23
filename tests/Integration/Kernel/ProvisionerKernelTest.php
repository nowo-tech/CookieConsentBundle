<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigReaderInterface;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Provisioning\CookieConsentProvisionerInterface;
use Nowo\CookieConsentBundle\Provisioning\ProvisioningResult;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote;
use Nowo\CookieConsentBundle\Tests\Support\Host\HostConsentSeeder;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function gc_collect_cycles;
use function memory_get_usage;

/**
 * 1.36 provisioning port through a real kernel: a DDD host's application service autowired with the port (no entity,
 * no manager) seeds the default profile and the cookie inventory from decoded fixtures, in every
 * `doctrine.private_entity_manager` mode and with a dedicated `doctrine.entity_manager`. The application's pending
 * changes are never flushed, a second run reports everything unchanged, the read port sees the result, and a
 * kept-alive kernel (FrankenPHP worker mode, kernel reset off) keeps no state between calls.
 */
final class ProvisionerKernelTest extends TestCase
{
    private const PROFILE = [
        'name'                    => 'Beacon demo',
        'colorTheme'              => 'dark',
        'consentModalLayout'      => 'box',
        'consentModalVariant'     => 'wide',
        'granularCookieSelection' => true,
        'translations'            => [
            'en' => ['consentModalTitle' => 'Cookies', 'consentModalDescription' => 'We use cookies.', 'consentModalAcceptAllBtn' => 'Accept all', 'consentModalAcceptNecessaryBtn' => 'Only necessary'],
            'es' => ['consentModalTitle' => 'Cookies', 'consentModalDescription' => 'Usamos cookies.', 'consentModalAcceptAllBtn' => 'Aceptar', 'consentModalAcceptNecessaryBtn' => 'Solo necesarias'],
        ],
    ];

    private const COOKIES = [
        ['name' => 'PHPSESSID', 'category' => 'required', 'duration' => 'Session', 'translations' => ['en' => ['provider' => 'Beacon', 'purpose' => 'Session']]],
        ['name' => '_ga', 'category' => 'analytics', 'duration' => '2 years', 'type' => 'third_party', 'sort_order' => 1, 'translations' => ['en' => ['provider' => 'Google', 'purpose' => 'Statistics']]],
    ];

    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    /**
     * @return iterable<string, array{0: bool|null}>
     */
    public static function modes(): iterable
    {
        yield 'private_entity_manager: true' => [true];
        yield 'private_entity_manager: false (deprecated)' => [false];
    }

    #[DataProvider('modes')]
    public function testADddHostSeedsThroughThePortAndItsPendingChangesStayItsOwn(?bool $private): void
    {
        $this->boot(['doctrine' => ['private_entity_manager' => $private]]);
        $host = $this->manager('default');

        // The application left a change pending in its own manager (its handler has not committed yet).
        $note = new HostNote();
        $host->persist($note);

        [$profile, $cookies] = $this->seeder()->seed(self::PROFILE, self::COOKIES);
        self::assertSame([1, 0, 0], $this->counts($profile));
        self::assertSame([2, 0, 0], $this->counts($cookies));

        $connection = $host->getConnection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'), 'the application\'s pending insert was not flushed');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note), 'and it is still the application\'s to commit');
        self::assertSame([], $host->getUnitOfWork()->getIdentityMap(), 'no bundle row entered the application\'s manager');
        self::assertFalse($this->provisioningManager()->isCreated(), 'the provisioning manager is dropped after every call');
        self::assertSame(['PHPSESSID', '_ga'], $connection->fetchFirstColumn('SELECT name FROM dashboard_cookie_definition ORDER BY sort_order, name'));

        $reader = $this->service(CookieConsentConfigReaderInterface::class);
        self::assertInstanceOf(CookieConsentConfigReaderInterface::class, $reader);
        self::assertSame($profile->profileId, $reader->defaultProfileId(), 'read access: the read port');
        self::assertSame('Beacon demo', $reader->defaultProfile('en')?->name);

        [$profile, $cookies] = $this->seeder()->seed(self::PROFILE, self::COOKIES);
        self::assertSame([0, 0, 1], $this->counts($profile), 'idempotent');
        self::assertSame([0, 0, 2], $this->counts($cookies));

        $host->flush();
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'));
    }

    public function testADedicatedEntityManagerIsTheSource(): void
    {
        $this->boot(['doctrine' => ['entity_manager' => 'cookie_consent']], [
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
        ], 'cookie_consent');

        [$profile] = $this->seeder()->seed(self::PROFILE, self::COOKIES);

        self::assertSame(1, $profile->created);
        self::assertSame('Beacon demo', $this->manager('cookie_consent')->getConnection()->fetchOne('SELECT name FROM dashboard_cookie_config'));
    }

    /**
     * FrankenPHP worker mode with the kernel kept alive: 300 seeding runs on the same container; another process edits
     * the rows between some runs. Every run sees the database as it is (the edit is reported as an update and put
     * back), no entity survives a run, and memory stays flat after warm-up.
     */
    public function testAKeptAliveWorkerKeepsNoStateBetweenRuns(): void
    {
        $this->boot(['doctrine' => ['private_entity_manager' => true]]);
        $connection = $this->manager('default')->getConnection();
        $seeder     = $this->seeder();
        $manager    = $this->provisioningManager();
        $seeder->seed(self::PROFILE, self::COOKIES);

        $baseline = 0;
        for ($i = 0; $i < 300; ++$i) {
            $edited = $i % 25 === 0;
            if ($edited) {
                $connection->executeStatement('UPDATE dashboard_cookie_config SET color_theme = ?', ['light']);
                $connection->executeStatement('UPDATE dashboard_cookie_definition SET duration = ? WHERE name = ?', ['1 day', '_ga']);
            }

            [$profile, $cookies] = $seeder->seed(self::PROFILE, self::COOKIES);
            self::assertSame($edited ? [0, 1, 0] : [0, 0, 1], $this->counts($profile), 'run #' . $i);
            self::assertSame($edited ? [0, 1, 1] : [0, 0, 2], $this->counts($cookies), 'run #' . $i);
            self::assertFalse($manager->isCreated(), 'dropped after run #' . $i);

            if ($i === 50) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $baseline, 'Memory must stay flat after warm-up.');
        self::assertSame('dark', $connection->fetchOne('SELECT color_theme FROM dashboard_cookie_config'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM dashboard_cookie_config'));
    }

    /**
     * @param array<string, mixed> $bundleConfig
     * @param array<string, mixed>|null $doctrineConfig
     */
    private function boot(array $bundleConfig, ?array $doctrineConfig = null, string $schemaManager = 'default'): void
    {
        $this->kernel = new CookieConsentTestKernel(false, $bundleConfig + ['use_database_config' => true, 'web_ui' => ['enabled' => false]], doctrineConfig: $doctrineConfig ?? [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['mappings' => ['Host' => [
                'type'      => 'attribute',
                'is_bundle' => false,
                'dir'       => __DIR__ . '/HostEntity',
                'prefix'    => 'Nowo\\CookieConsentBundle\\Tests\\Integration\\Kernel\\HostEntity',
            ]]],
        ], publicIds: [CookieConsentConfigReaderInterface::class, CookieConsentProvisionerInterface::class, NowoCookieConsentExtension::PROVISIONER_ENTITY_MANAGER_SERVICE], hostServiceClasses: [HostConsentSeeder::class => HostConsentSeeder::class]);
        $this->kernel->boot();

        $em = $this->manager($schemaManager);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    private function seeder(): HostConsentSeeder
    {
        $seeder = $this->service(HostConsentSeeder::class);
        self::assertInstanceOf(HostConsentSeeder::class, $seeder);

        return $seeder;
    }

    private function provisioningManager(): PrivateEntityManager
    {
        $manager = $this->service(NowoCookieConsentExtension::PROVISIONER_ENTITY_MANAGER_SERVICE);
        self::assertInstanceOf(PrivateEntityManager::class, $manager);

        return $manager;
    }

    private function manager(string $name): EntityManagerInterface
    {
        $registry = $this->service('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);
        $manager = $registry->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function service(string $id): object
    {
        self::assertNotNull($this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container->get($id);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function counts(ProvisioningResult $result): array
    {
        return [$result->created, $result->updated, $result->unchanged];
    }
}
