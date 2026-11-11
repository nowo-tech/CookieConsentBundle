<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\Ddd;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;

use function gc_collect_cycles;
use function memory_get_usage;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 1.30 (REQ-ARCH-001 rule 3): a DDD host without Doctrine writes the consent cookies through its own
 * {@see ConsentCookieWriterInterface} (no Request, no Response in its code); the bundle applies the returned jar to the
 * response. Kept-alive kernel (`http_kernel`, no services resetter) for two visitors in alternation: each response
 * carries only that visitor's cookies and memory stays flat. The standard host keeps the default `CookieHandler`.
 */
final class HostCookieWriterKernelTest extends TestCase
{
    private const CONFIG = ['csrf_protection' => false];

    private ?HostWithoutDoctrineKernel $kernel = null;

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored in tearDown)
        set_error_handler(function (int $level, string $message): bool {
            if (str_contains($message, 'cookie-consent-bundle')) {
                $this->deprecations[] = $message;
            }

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(HostWithoutDoctrineKernel::cacheRoot());
    }

    public function testStandardHostKeepsTheDefaultHandler(): void
    {
        $container = $this->boot(false);

        self::assertInstanceOf(CookieHandler::class, $container->get(ConsentCookieWriterInterface::class));

        $answer = $this->handle($this->answer('alice', 'use_all_cookies'));
        self::assertContains('Cookie_Consent_Key', $this->names($answer));
    }

    public function testHostWriterSetsItsCookiesForTwoVisitorsInAKeptAliveKernel(): void
    {
        $container = $this->boot(true);
        $writer    = $container->get(HostConsentCookieWriter::class);
        self::assertInstanceOf(HostConsentCookieWriter::class, $writer);
        $before = 0;

        for ($i = 0; $i < 400; ++$i) {
            $visitor  = $i % 2 === 0 ? 'alice' : 'bob';
            $choice   = $visitor === 'alice' ? 'use_all_cookies' : 'use_only_functional_cookies';
            $response = $this->handle($this->answer($visitor, $choice));

            $cookies = $this->byName($response);
            self::assertSame(['Host_Consent_Key', 'Host_Analytics'], array_keys($cookies), 'only the host cookies');
            self::assertSame('key-' . $visitor, $cookies['Host_Consent_Key']->getValue());
            self::assertSame($visitor === 'alice' ? 'yes' : 'no', $cookies['Host_Analytics']->getValue());

            if ($i === 99) {
                gc_collect_cycles();
                $before = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before, 'Memory must stay flat over 300 requests.');
        self::assertSame(400, $writer->calls);
        self::assertSame([], $this->deprecations, 'the new port triggers no bundle deprecation');
    }

    private function answer(string $visitor, string $choice): Request
    {
        return Request::create('/cookie_consent', 'POST', ['cookie_consent' => [$choice => '']], ['Cookie_Consent_Key' => 'key-' . $visitor]);
    }

    private function boot(bool $hostCookieWriter): ContainerInterface
    {
        $this->kernel = new HostWithoutDoctrineKernel(self::CONFIG, true, $hostCookieWriter);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function handle(Request $request): Response
    {
        self::assertNotNull($this->kernel);
        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);
        $response = $http->handle($request);
        $http->terminate($request, $response);

        return $response;
    }

    /**
     * @return array<string, Cookie>
     */
    private function byName(Response $response): array
    {
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }

        return $cookies;
    }

    /**
     * @return list<string>
     */
    private function names(Response $response): array
    {
        return array_keys($this->byName($response));
    }
}
