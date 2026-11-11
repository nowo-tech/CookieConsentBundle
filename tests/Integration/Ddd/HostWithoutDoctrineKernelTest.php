<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Ddd;

use Nowo\CookieConsentBundle\Cookie\CookieLoggerInterface;
use Nowo\CookieConsentBundle\Cookie\NullCookieLogger;
use Nowo\CookieConsentBundle\Tests\Support\Host\HostConsentStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;

use function gc_collect_cycles;
use function memory_get_usage;

/**
 * 1.28 (architecture bar rule 8): a DDD host without Doctrine — DoctrineBundle not registered — renders the consent
 * modal from YAML (copy and cookie inventory) and logs consents into its own store (`CookieLoggerInterface` aliased),
 * in a kept-alive kernel (FrankenPHP worker mode, kernel reset off: `http_kernel` handle / terminate, no services
 * resetter) for two visitors without mixing their choices, with flat memory. The standard host (DoctrineBundle,
 * attribute entities) is covered by the other kernel tests.
 */
final class HostWithoutDoctrineKernelTest extends TestCase
{
    private const CONFIG = [
        'csrf_protection'      => false,
        'use_cookie_inventory' => true,
        'cookie_inventory'     => [[
            'name'         => 'host_stats',
            'category'     => 'analytics',
            'duration'     => '1 year',
            'translations' => ['en' => ['provider' => 'Host', 'purpose' => 'Statistics']],
        ]],
    ];

    private ?HostWithoutDoctrineKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(HostWithoutDoctrineKernel::cacheRoot());
    }

    public function testRendersFromYamlAndLogsIntoTheHostStore(): void
    {
        $container = $this->boot(self::CONFIG);
        self::assertFalse($container->has('doctrine'));
        $store = $container->get(CookieLoggerInterface::class);
        self::assertInstanceOf(HostConsentStore::class, $store);

        $page = $this->handle(Request::create('/cookie_consent'));
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('host_stats', (string) $page->getContent());

        $answer = $this->handle(Request::create('/cookie_consent', 'POST', ['cookie_consent' => ['use_all_cookies' => '']]));
        self::assertLessThan(500, $answer->getStatusCode());
        self::assertSame(1, $store->writes);
    }

    public function testKeptAliveKernelKeepsTwoVisitorsApart(): void
    {
        $container = $this->boot(self::CONFIG);
        $store     = $container->get(HostConsentStore::class);
        self::assertInstanceOf(HostConsentStore::class, $store);
        $before = 0;

        for ($i = 0; $i < 600; ++$i) {
            $visitor = $i % 2 === 0 ? 'alice' : 'bob';
            $choice  = $visitor === 'alice' ? 'use_all_cookies' : 'use_only_functional_cookies';
            $request = Request::create('/cookie_consent', 'POST', ['cookie_consent' => [$choice => '']], ['Cookie_Consent_Key' => 'key-' . $visitor]);
            $this->handle($request);

            if ($i === 99) {
                gc_collect_cycles();
                $before = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before, 'Memory must stay flat over 500 requests.');

        self::assertSame(600, $store->writes);
        self::assertCount(2, $store->byKey);
        $choices = array_values($store->byKey);
        self::assertNotEquals($choices[0], $choices[1], 'each visitor keeps their own choices');
    }

    public function testTheBundleLoggerWithoutDoctrineFailsClearly(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('use_logger');

        $this->boot(['csrf_protection' => false], hostLogger: false);
    }

    public function testWithoutTheLogLoggingIsANoOp(): void
    {
        $container = $this->boot(['csrf_protection' => false, 'use_logger' => false], hostLogger: false);
        self::assertInstanceOf(NullCookieLogger::class, $container->get(CookieLoggerInterface::class));

        $answer = $this->handle(Request::create('/cookie_consent', 'POST', ['cookie_consent' => ['use_all_cookies' => '']]));
        self::assertLessThan(500, $answer->getStatusCode());
        // Admin pages edit the tables: without Doctrine they answer 404.
        self::assertSame(404, $this->handle(Request::create('/cookie-consent-config/1/cookies'))->getStatusCode());
    }

    public function testDatabaseConfigWithoutDoctrineFailsClearly(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('use_database_config: true needs Doctrine ORM');

        $this->boot(['use_database_config' => true]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config, bool $hostLogger = true): ContainerInterface
    {
        $this->kernel = new HostWithoutDoctrineKernel($config, $hostLogger);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    /**
     * One request through `http_kernel` (no services resetter in between).
     */
    private function handle(Request $request): Response
    {
        self::assertNotNull($this->kernel);
        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);
        $response = $http->handle($request);
        $http->terminate($request, $response);

        return $response;
    }
}
