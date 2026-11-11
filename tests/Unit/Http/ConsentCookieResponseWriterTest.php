<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Http;

use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Http\ConsentCookieResponseWriter;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\LegacyCookieHandler;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\SubclassCookieHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * 1.30 (REQ-ARCH-001 rule 3): which consent cookie port the bundle listeners call.
 */
final class ConsentCookieResponseWriterTest extends TestCase
{
    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        $this->deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored in tearDown)
        set_error_handler(function (int $level, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testDefaultHandlerUsesTheNewPortWithoutDeprecation(): void
    {
        $handler  = new CookieHandler(true, new SystemClock());
        $response = new Response();

        (new ConsentCookieResponseWriter($handler, $handler))->save(['analytics' => true], 'k', $response);
        (new ConsentCookieResponseWriter($handler))->clear(Request::create('/', 'GET', [], ['Cookie_Consent' => 'x']), $response);

        self::assertSame(['Cookie_Consent', 'Cookie_Consent_Key', 'Cookie_Category_analytics'], $this->names($response));
        self::assertSame([], $this->deprecations);
    }

    public function testHostWriterWinsOverTheDefaultHandler(): void
    {
        $writer   = new HostConsentCookieWriter();
        $response = new Response();

        $bridge = new ConsentCookieResponseWriter(new CookieHandler(true, new SystemClock()), $writer);
        $bridge->save(['analytics' => 'true'], 'visitor-1', $response);
        $bridge->clear(Request::create('/', 'GET', [], ['Host_Analytics' => 'yes']), $response);

        self::assertSame(['Host_Consent_Key', 'Host_Analytics'], $this->names($response));
        self::assertSame(2, $writer->calls);
        self::assertSame([], $this->deprecations);
    }

    public function testLegacyHostHandlerWithoutAWriterKeepsTheDeprecatedPath(): void
    {
        $response = new Response();

        $bridge = new ConsentCookieResponseWriter(new LegacyCookieHandler());
        $bridge->save(['analytics' => true], 'visitor-1', $response);
        self::assertSame(['Legacy_Key'], $this->names($response));

        $bridge->clear(Request::create('/'), $response);
        self::assertTrue($response->headers->getCookies()[0]->isCleared());

        // The container reports the deprecation once, at build (ConsentCookieWriterPass), not per request.
        self::assertSame([], $this->deprecations);
    }

    public function testSubclassOfTheDefaultHandlerKeepsItsOverride(): void
    {
        $response = new Response();

        (new ConsentCookieResponseWriter(new SubclassCookieHandler(true, new SystemClock())))->save([], 'k', $response);

        self::assertSame(
            ['Cookie_Consent', 'Sub_Cookie_Consent', 'Cookie_Consent_Key', 'Sub_Cookie_Consent_Key'],
            $this->names($response),
        );
    }

    /**
     * @return list<string>
     */
    private function names(Response $response): array
    {
        return array_values(array_map(static fn (Cookie $c): string => $c->getName(), $response->headers->getCookies()));
    }
}
