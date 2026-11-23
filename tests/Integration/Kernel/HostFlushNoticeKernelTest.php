<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentHostFlushNoticePass;
use Nowo\CookieConsentBundle\DependencyInjection\NowoCookieConsentExtension;
use Nowo\CookieConsentBundle\Doctrine\CookieConsentHostFlushReporter;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieDefinition;
use Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use function array_filter;
use function array_values;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;
use function uniqid;

use const E_USER_DEPRECATED;

/**
 * 1.35 (REQ-ARCH-001 rule 6 deprecation layer) through a real kernel (Doctrine on one SQLite database, a host entity
 * mapped on the same default manager):
 * - the `doctrine.private_entity_manager` notice is triggered once at build exactly when the bundle writes through the
 *   application's default manager (unset / `false`, `doctrine.entity_manager` unset or naming the default one);
 * - in that mode the admin saves report the application's pending changes they flush (transaction-doctrine's
 *   host-flush probe) and still flush them, as in 1.34 (behaviour unchanged);
 * - the reporter is reset at every request (worker mode with the kernel kept alive);
 * - with `private_entity_manager: true` or a dedicated manager there is no notice and no probe.
 */
final class HostFlushNoticeKernelTest extends TestCase
{
    private const PROBE_LINE = 'Your pending changes to Nowo\CookieConsentBundle\Tests\Integration\Kernel\HostEntity\HostNote were flushed by NowoCookieConsentBundle; 2.0 no longer does.';

    private ?CookieConsentTestKernel $kernel = null;

    /** @var list<string> */
    private array $deprecations = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testUnsetNoticesAtBuildAndTheAdminSavesStillFlushButReportTheApplicationsChanges(): void
    {
        $configId = $this->boot([]);
        self::assertSame(['Since nowo-tech/cookie-consent-bundle 1.35: ' . NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION], $this->bundleNotices());

        $host       = $this->hostManager();
        $connection = $host->getConnection();
        $browser    = $this->browser();

        // Create (CookieDefinitionAdminController::new): the application left an insert pending in its manager.
        $host->persist(new HostNote());
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));
        $this->keepAlive();
        $lines = $this->capture(static function () use ($browser, $crawler): void {
            $browser->submit($crawler->filter('form[name="cookie_definition"]')->form(), ['cookie_definition[name]' => '_gid']);
        });
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        self::assertSame([self::PROBE_LINE], $lines);
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'), '1.x behaviour unchanged: the bundle flush wrote it');

        // Profile settings save (CookieConsentConfigRepository::saveSettings): next request, the reporter was reset.
        $host->persist(new HostNote());
        $this->keepAlive();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/settings/profile', $configId));
        $this->keepAlive();
        $nameField = (string) $crawler->filter('input[name$="[name]"]')->attr('name');
        $form      = $crawler->filter('input[name$="[name]"]')->closest('form')?->form();
        self::assertNotNull($form);
        $lines = $this->capture(static function () use ($browser, $form, $nameField): void {
            $browser->submit($form, [$nameField => 'Saved on the host manager']);
        });
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        self::assertSame([self::PROBE_LINE], $lines, 'reported again: a new unit of work');
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'));
        self::assertSame('Saved on the host manager', $connection->fetchOne('SELECT name FROM dashboard_cookie_config WHERE id = ?', [$configId]));

        // Cookie definition edit (CookieDefinitionRepository::saveEdit).
        $definitionId = (int) $connection->fetchOne('SELECT id FROM dashboard_cookie_definition WHERE name = ?', ['_ga']);
        $host->persist(new HostNote());
        $this->keepAlive();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/%d/edit', $configId, $definitionId));
        $this->keepAlive();
        $lines = $this->capture(static function () use ($browser, $crawler): void {
            $browser->submit($crawler->filter('form[name="cookie_definition"]')->form(), ['cookie_definition[name]' => '_ga_edited']);
        });
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        self::assertSame([self::PROBE_LINE], $lines);
        self::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM host_note'));

        // Without pending application changes, a save reports nothing.
        $this->keepAlive();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));
        $this->keepAlive();
        $lines = $this->capture(static function () use ($browser, $crawler): void {
            $browser->submit($crawler->filter('form[name="cookie_definition"]')->form(), ['cookie_definition[name]' => '_fbp']);
        });
        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode());
        self::assertSame([], $lines);
    }

    public function testFalseNoticesAtBuild(): void
    {
        $this->boot(['private_entity_manager' => false]);

        self::assertSame(['Since nowo-tech/cookie-consent-bundle 1.35: ' . NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_FALSE_DEPRECATION], $this->bundleNotices());
        self::assertTrue($this->container()->has(CookieConsentHostFlushNoticePass::BOUNDARY_SUBSCRIBER_SERVICE));
    }

    public function testAnEntityManagerNamingTheDefaultOneIsHostModeToo(): void
    {
        $this->boot(['entity_manager' => 'default']);

        self::assertSame(['Since nowo-tech/cookie-consent-bundle 1.35: ' . NowoCookieConsentExtension::PRIVATE_ENTITY_MANAGER_UNSET_DEPRECATION], $this->bundleNotices());
    }

    public function testThePrivateManagerHasNoNoticeAndNoProbe(): void
    {
        $configId = $this->boot(['private_entity_manager' => true]);
        self::assertSame([], $this->bundleNotices());
        self::assertFalse($this->container()->has(CookieConsentHostFlushReporter::class));
        self::assertFalse($this->container()->has(CookieConsentHostFlushNoticePass::BOUNDARY_SUBSCRIBER_SERVICE));

        $host = $this->hostManager();
        $note = new HostNote();
        $host->persist($note);
        $browser = $this->browser();
        $crawler = $browser->request('GET', sprintf('/cookie-consent-config/%d/cookies/new', $configId));
        $this->keepAlive();
        $lines = $this->capture(static function () use ($browser, $crawler): void {
            $browser->submit($crawler->filter('form[name="cookie_definition"]')->form(), ['cookie_definition[name]' => '_gid']);
        });

        self::assertSame(Response::HTTP_FOUND, $browser->getResponse()->getStatusCode());
        self::assertSame([], $lines);
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'), 'unchanged: never flushed by the bundle');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note));
    }

    public function testADedicatedManagerHasNoNotice(): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_database_config' => true,
            'web_ui'              => ['enabled' => true],
            'security'            => ['allow_unauthenticated' => true],
            'doctrine'            => ['entity_manager' => 'cookie_consent'],
        ], hostParameters: ['cc_notice_build' => uniqid('', true)], doctrineConfig: [
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
        $this->bootCapturing();

        self::assertSame([], $this->bundleNotices());
        self::assertFalse($this->container()->has(CookieConsentHostFlushReporter::class));
    }

    /**
     * @param array<string, mixed> $doctrine the bundle's `doctrine` options
     */
    private function boot(array $doctrine): int
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_database_config' => true,
            'web_ui'              => ['enabled' => true],
            'security'            => ['allow_unauthenticated' => true],
            'doctrine'            => $doctrine,
        ], hostParameters: ['cc_notice_build' => uniqid('', true)], doctrineConfig: [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['mappings' => ['Host' => [
                'type'      => 'attribute',
                'is_bundle' => false,
                'dir'       => __DIR__ . '/HostEntity',
                'prefix'    => 'Nowo\\CookieConsentBundle\\Tests\\Integration\\Kernel\\HostEntity',
            ]]],
        ]);
        $this->bootCapturing();

        $em = $this->hostManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $config = (new CookieConsentConfig())->setName('Public site')->setEnabled(true)->setDefault(true);
        $em->persist($config);
        $em->persist((new CookieDefinition())->setName('_ga')->setCategory('analytics')->setConfig($config));
        $em->flush();

        return (int) $config->getId();
    }

    /** Boots a freshly built container (unique host parameter) and keeps the deprecations of its build. */
    private function bootCapturing(): void
    {
        self::assertNotNull($this->kernel);
        $kernel             = $this->kernel;
        $this->deprecations = $this->capture(static function () use ($kernel): void {
            $kernel->boot();
        }, false);
    }

    /**
     * @return list<string> the bundle's private_entity_manager notices triggered by the build
     */
    private function bundleNotices(): array
    {
        return array_values(array_filter($this->deprecations, static fn (string $message): bool => str_contains($message, 'nowo_cookie_consent.doctrine.private_entity_manager')));
    }

    /**
     * @param callable(): void $call
     *
     * @return list<string> the deprecations `$call` triggered (only the probe lines when `$probeOnly`)
     */
    private function capture(callable $call, bool $probeOnly = true): array
    {
        $deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored below)
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $call();
        } finally {
            restore_error_handler();
        }

        if (!$probeOnly) {
            return $deprecations;
        }

        return array_values(array_filter($deprecations, static fn (string $message): bool => str_contains($message, 'were flushed by NowoCookieConsentBundle')));
    }

    private function browser(): KernelBrowser
    {
        self::assertNotNull($this->kernel);
        $browser = new KernelBrowser($this->kernel);
        $browser->disableReboot();

        return $browser;
    }

    /**
     * FrankenPHP worker mode with the kernel reset off: the next request does not run `services_resetter`.
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

    private function hostManager(): EntityManagerInterface
    {
        $em = $this->container()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
