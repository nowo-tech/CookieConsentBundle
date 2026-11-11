<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\CookiePorts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;

use function array_map;
use function array_values;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * Audit MR-C-M1 / M2 / L1 (1.30): every 1.29 way of customising the cookie handler keeps working (no TypeError, an
 * overridden `saveCookie()` still runs) and reports one deprecation at container build, not per request; the 1.30
 * ports are used without a deprecation.
 */
final class CookiePortsKernelTest extends TestCase
{
    private const STANDARD = ['Cookie_Consent', 'Cookie_Consent_Key'];

    private ?CookiePortsKernel $kernel = null;

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
        (new Filesystem())->remove(CookiePortsKernel::cacheRoot());
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>, bool}>
     */
    public static function modes(): iterable
    {
        yield 'standard host' => ['default', self::STANDARD, [], false];
        yield 'decorator on CookieHandler::class' => ['decorate_concrete', self::STANDARD, ['Decorated'], true];
        yield 'decorator on CookieHandlerInterface' => ['decorate_interface', self::STANDARD, ['Decorated'], true];
        yield 'CookieHandler class replaced by a subclass' => ['replace_subclass', self::STANDARD, ['Sub_Cookie_Consent_Key'], true];
        yield 'CookieHandlerInterface aliased to a subclass' => ['alias_subclass', self::STANDARD, ['Sub_Cookie_Consent_Key'], true];
        yield 'host handler implementing both ports' => ['dual', [], ['Dual_Key'], false];
        yield 'host writer wins over a legacy decorator' => ['host_writer', [], ['Host_Consent_Key', 'Host_Analytics'], false];
    }

    /**
     * @param list<string> $expected cookie names that must be set
     * @param list<string> $extra host cookie names that must be set
     */
    #[DataProvider('modes')]
    public function testEveryCustomisationAnswersAndKeepsItsCookies(string $mode, array $expected, array $extra, bool $deprecated): void
    {
        $this->kernel = new CookiePortsKernel($mode);
        $this->kernel->boot();
        self::assertCount($deprecated ? 1 : 0, $this->deprecations, 'one deprecation at container build, or none');

        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);

        for ($i = 0; $i < 3; ++$i) {
            $request  = Request::create('/cookie_consent', 'POST', ['cookie_consent' => ['use_all_cookies' => '']], ['Cookie_Consent_Key' => 'key-1']);
            $response = $http->handle($request, catch: false);
            $http->terminate($request, $response);

            self::assertLessThan(500, $response->getStatusCode());
            $names = array_values(array_map(static fn (Cookie $c): string => $c->getName(), $response->headers->getCookies()));
            foreach ([...$expected, ...$extra] as $name) {
                self::assertContains($name, $names, $mode);
            }
        }

        self::assertCount($deprecated ? 1 : 0, $this->deprecations, 'no deprecation per request');
    }
}
