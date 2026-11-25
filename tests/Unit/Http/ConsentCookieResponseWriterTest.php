<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Http;

use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Http\ConsentCookieResponseWriter;
use Nowo\CookieConsentBundle\Tests\Support\HostPorts\HostConsentCookieWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_map;
use function array_values;

/**
 * 1.30 / 2.0 (REQ-ARCH-001 rule 3): the listeners write what the consent cookie port returns.
 */
final class ConsentCookieResponseWriterTest extends TestCase
{
    public function testTheBundleWriterSetsAndExpiresTheConsentCookies(): void
    {
        $response = new Response();
        $bridge   = new ConsentCookieResponseWriter(new CookieHandler(true, new SystemClock()));

        $bridge->save(['analytics' => true], 'k', $response);
        $bridge->clear(Request::create('/', 'GET', [], ['Cookie_Consent' => 'x', 'Cookie_Consent_Key' => 'k']), $response);

        self::assertSame(['Cookie_Consent', 'Cookie_Consent_Key', 'Cookie_Category_analytics'], $this->names($response));
        self::assertTrue($response->headers->getCookies()[0]->isCleared(), 'the expired Cookie_Consent replaces the set one');
        self::assertFalse($response->headers->getCookies()[1]->isCleared(), 'Cookie_Consent_Key is never expired');
    }

    public function testAHostWriterDecidesTheCookies(): void
    {
        $writer   = new HostConsentCookieWriter();
        $response = new Response();

        $bridge = new ConsentCookieResponseWriter($writer);
        $bridge->save(['analytics' => 'true'], 'visitor-1', $response);
        $bridge->clear(Request::create('/', 'GET', [], ['Host_Analytics' => 'yes']), $response);

        self::assertSame(['Host_Consent_Key', 'Host_Analytics'], $this->names($response));
        self::assertSame(2, $writer->calls);
    }

    /**
     * @return list<string>
     */
    private function names(Response $response): array
    {
        return array_values(array_map(static fn (Cookie $c): string => $c->getName(), $response->headers->getCookies()));
    }
}
