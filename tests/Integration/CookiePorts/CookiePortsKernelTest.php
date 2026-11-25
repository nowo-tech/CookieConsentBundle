<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Integration\CookiePorts;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;

use function array_map;
use function array_values;
use function md5;

/**
 * The consent cookie port in a real kernel (audit MR-C-M1 / M2, 2.0 lesson W1-1): the supported customisations answer
 * with their cookies on every request; every 1.x customisation that 2.0 no longer calls fails the container build with
 * a message naming what to do, instead of being dropped silently.
 */
final class CookiePortsKernelTest extends TestCase
{
    private const STANDARD = ['Cookie_Consent', 'Cookie_Consent_Key'];

    private const REMOVED_PORT = 'Nowo\\CookieConsentBundle\\Cookie\\CookieHandlerInterface';

    private ?CookiePortsKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        (new Filesystem())->remove(CookiePortsKernel::cacheRoot());
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function supported(): iterable
    {
        yield 'standard host' => ['default', self::STANDARD, []];
        yield 'host writer aliased to the port' => ['host_writer', [], ['Host_Consent_Key', 'Host_Analytics']];
        yield 'decorator of nowo_cookie_consent.cookie_writer' => ['decorate_writer', self::STANDARD, ['Decorated']];
        yield 'host writer, CookieHandler decorated too' => ['decorate_handler_with_host_writer', [], ['Host_Consent_Key', 'Host_Analytics']];
        yield 'unrelated missing interface in an autodiscovered class' => ['discover:UnrelatedMissing', self::STANDARD, []];
    }

    /**
     * @param list<string> $expected cookie names that must be set
     * @param list<string> $extra host cookie names that must be set
     */
    #[DataProvider('supported')]
    public function testSupportedCustomisationsAnswerWithTheirCookies(string $mode, array $expected, array $extra): void
    {
        $this->kernel = new CookiePortsKernel($mode);
        $this->kernel->boot();

        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);

        for ($i = 0; $i < 3; ++$i) {
            $request  = Request::create('/cookie_consent', 'POST', ['cookie_consent' => ['use_all_cookies' => '']], ['Cookie_Consent_Key' => md5('key-1')]);
            $response = $http->handle($request, catch: false);
            $http->terminate($request, $response);

            self::assertLessThan(500, $response->getStatusCode());
            $names = array_values(array_map(static fn (Cookie $c): string => $c->getName(), $response->headers->getCookies()));
            foreach ([...$expected, ...$extra] as $name) {
                self::assertContains($name, $names, $mode);
            }
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function removed(): iterable
    {
        yield 'decorator of CookieHandler' => ['decorate_handler', ['"Nowo\\CookieConsentBundle\\Cookie\\CookieHandler" service is decorated or replaced', 'nowo_cookie_consent.cookie_writer']];
        yield 'alias of the removed port id' => ['alias_removed_port', [self::REMOVED_PORT, 'deprecated since nowo-tech/cookie-consent-bundle 1.30']];
        yield 'autodiscovered class implementing the removed port' => ['discover:ImplementsRemoved', ['Tests\\Fixtures\\RemovedPorts\\ImplementsRemoved\\LegacyCookieHandler', self::REMOVED_PORT, 'Loader error']];
        yield '#[AsAlias] of the removed port' => ['discover:AsAliasRemoved', [self::REMOVED_PORT, 'ConsentCookieWriterInterface']];
        yield '#[AsDecorator] of the removed port (IGNORE on invalid)' => ['discover:AsDecoratorRemoved', ['RemovedPorts\\AsDecoratorRemoved\\DecoratingCookieWriter" decorates', self::REMOVED_PORT]];
        yield 'autodiscovered form type extending the removed settings type' => ['discover:ExtendsRemovedForm', ['Nowo\\CookieConsentBundle\\Form\\CookieConsentConfigSettingsType', 'CookieConsentConfigFullSettingsType']];
    }

    /**
     * @param list<string> $fragments parts of the build error
     */
    #[DataProvider('removed')]
    public function testRemovedCustomisationsFailTheBuild(string $mode, array $fragments): void
    {
        $this->kernel = new CookiePortsKernel($mode);

        try {
            $this->kernel->boot();
            self::fail('The container build must fail for ' . $mode);
        } catch (LogicException $e) {
            foreach ([...$fragments, 'UPGRADING.md "To 2.0.0"'] as $fragment) {
                self::assertStringContainsString($fragment, $e->getMessage());
            }
        }
    }
}
