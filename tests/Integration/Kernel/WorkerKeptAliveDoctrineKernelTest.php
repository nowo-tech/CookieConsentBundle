<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;

use function gc_collect_cycles;
use function md5;
use function memory_get_usage;

/**
 * FrankenPHP worker mode with the kernel kept alive (FRANKENPHP_RESET_KERNEL=false) on a Doctrine host (DoctrineBundle,
 * SQLite, the bundle's own `CookieLogger`): ONE booted kernel serves 1,200 consent submissions through `http_kernel`
 * (handle + terminate), alternating two visitors with different choices and IPs, and `services_resetter` is never
 * called (`Kernel::handle()` would run it). Every logged row carries its own visitor's key, choice and anonymised IP,
 * the shared EntityManager never holds a log row, and memory growth after warm-up stays under 4 MiB.
 */
final class WorkerKeptAliveDoctrineKernelTest extends TestCase
{
    private const REQUESTS = 1200;

    private const WARMUP = 200;

    private ?CookieConsentTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testAKeptAliveDoctrineKernelKeepsTwoVisitorsApartWithFlatMemory(): void
    {
        $this->kernel = new CookieConsentTestKernel(false, [
            'use_logger'      => true,
            'csrf_protection' => false,
            'categories'      => ['analytics', 'marketing'],
            'web_ui'          => ['enabled' => false],
        ]);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $em = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $baseline = 0;
        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $alice   = $i % 2 === 0;
            $request = Request::create(
                '/cookie_consent',
                'POST',
                ['cookie_consent' => [$alice ? 'use_all_cookies' : 'use_only_functional_cookies' => '']],
                ['Cookie_Consent_Key' => $alice ? md5('key-alice') : md5('key-bob')],
                [],
                ['REMOTE_ADDR' => $alice ? '198.51.100.10' : '203.0.113.20'],
            );
            $response = $this->handle($request);
            self::assertLessThan(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode(), 'request #' . $i);
            self::assertSame(0, $em->getUnitOfWork()->size(), 'the shared EntityManager never holds a consent log row');

            if ($i === self::WARMUP) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(4 * 1024 * 1024, memory_get_usage() - $baseline, 'Memory must stay flat after warm-up.');

        $connection = $em->getConnection();
        $table      = $em->getClassMetadata(CookieConsentLog::class)->getTableName();
        $rows       = $connection->fetchAllAssociative('SELECT cookie_consent_key, ip_address, cookie_name, cookie_value FROM ' . $table);
        self::assertCount(self::REQUESTS * 2, $rows, 'every submission logged its two categories');
        foreach ($rows as $row) {
            if ($row['cookie_consent_key'] === md5('key-alice')) {
                self::assertSame(1, (int) $row['cookie_value'], 'alice accepted everything');
                self::assertStringStartsWith('198.51.100.', (string) $row['ip_address']);
            } else {
                self::assertSame(md5('key-bob'), $row['cookie_consent_key']);
                self::assertSame(0, (int) $row['cookie_value'], 'bob accepted functional cookies only');
                self::assertStringStartsWith('203.0.113.', (string) $row['ip_address']);
            }
        }
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
