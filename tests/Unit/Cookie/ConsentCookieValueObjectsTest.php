<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\Cookie\ConsentCookie;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;
use PHPUnit\Framework\TestCase;

/**
 * 1.30 (REQ-ARCH-001 rule 3): the framework-free consent cookie value objects.
 */
final class ConsentCookieValueObjectsTest extends TestCase
{
    public function testPersistentCookieKeepsTheBundleAttributes(): void
    {
        $expires = new DateTimeImmutable('2027-01-01 00:00:00');
        $cookie  = ConsentCookie::persistent('Cookie_Consent_Key', 'abc', $expires, true);

        self::assertSame('Cookie_Consent_Key', $cookie->name);
        self::assertSame('abc', $cookie->value);
        self::assertSame($expires, $cookie->expiresAt);
        self::assertTrue($cookie->httpOnly);
        self::assertFalse($cookie->expired);
        self::assertSame('/', $cookie->path);
        self::assertNull($cookie->domain);
        self::assertTrue($cookie->secure);
        self::assertSame('lax', $cookie->sameSite);
    }

    public function testExpiredCookieHasNoValueAndNoExpiry(): void
    {
        $cookie = ConsentCookie::expired('Cookie_Consent', false);

        self::assertTrue($cookie->expired);
        self::assertSame('', $cookie->value);
        self::assertNull($cookie->expiresAt);
        self::assertFalse($cookie->httpOnly);
    }

    public function testCookieNeedsAName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConsentCookie::expired('', true);
    }

    public function testJarIsImmutableAndKeepsTheOrder(): void
    {
        $empty = new ConsentCookieJar();
        $first = ConsentCookie::expired('a', true);
        $two   = $empty->with($first)->with(ConsentCookie::expired('b', true));

        self::assertTrue($empty->isEmpty());
        self::assertSame([], $empty->cookies());
        self::assertFalse($two->isEmpty());
        self::assertSame(['a', 'b'], array_map(static fn (ConsentCookie $c): string => $c->name, $two->cookies()));
        self::assertSame($first, $two->cookies()[0]);
    }

    public function testRequestCookiesKeepUniqueNamesOnly(): void
    {
        $cookies = new ConsentRequestCookies(['Cookie_Consent', 'PHPSESSID', 'Cookie_Consent', '42']);

        self::assertSame(['Cookie_Consent', 'PHPSESSID', '42'], $cookies->names());
        self::assertTrue($cookies->has('PHPSESSID'));
        self::assertTrue($cookies->has('42'));
        self::assertFalse($cookies->has('Cookie_Consent_Key'));
    }
}
