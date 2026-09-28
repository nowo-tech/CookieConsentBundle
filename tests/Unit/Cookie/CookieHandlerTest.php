<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class CookieHandlerTest extends TestCase
{
    public function testSavesConsentCookies(): void
    {
        $handler  = new CookieHandler(true, new SystemClock());
        $response = new Response();

        $handler->save([
            'required'  => true,
            'analytics' => true,
            'marketing' => false,
        ], 'consent-key', $response);

        $cookies = $response->headers->getCookies();
        $names   = array_map(static fn (Cookie $cookie): string => $cookie->getName(), $cookies);

        self::assertContains(CookieNameEnum::COOKIE_CONSENT_NAME, $names);
        self::assertContains(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, $names);
        self::assertContains(CookieNameEnum::getCookieCategoryName('analytics'), $names);
        self::assertContains(CookieNameEnum::getCookieCategoryName('marketing'), $names);
    }

    public function testSavesGranularCookies(): void
    {
        $handler  = new CookieHandler(false, new SystemClock());
        $response = new Response();

        $handler->save(
            ['required' => true, 'analytics' => true],
            'key',
            $response,
            ['_ga' => true, '' => false],
        );

        $cookies = $response->headers->getCookies();
        $names   = array_map(static fn (Cookie $cookie): string => $cookie->getName(), $cookies);

        self::assertContains(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, $names);
    }

    public function testUsesClockForExpiration(): void
    {
        $now   = new DateTimeImmutable('2026-01-15 12:00:00');
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $handler  = new CookieHandler(true, $clock);
        $response = new Response();
        $handler->save(['analytics' => true], 'key', $response);

        $cookies = $response->headers->getCookies();
        self::assertNotEmpty($cookies);
        self::assertSame($now->modify('+1 year')->getTimestamp(), $cookies[0]->getExpiresTime());
    }

    public function testConsentCookiesAreSecureHttpOnlyDecodedAndSameSiteLax(): void
    {
        $handler  = new CookieHandler(true, new SystemClock());
        $response = new Response();
        $handler->save(['analytics' => true], 'consent-key', $response);

        $byName = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $byName[$cookie->getName()] = $cookie;
        }

        $consent = $byName[CookieNameEnum::COOKIE_CONSENT_NAME];
        self::assertTrue($consent->isSecure());
        self::assertTrue($consent->isHttpOnly());
        self::assertFalse($consent->isRaw());
        self::assertSame(Cookie::SAMESITE_LAX, $consent->getSameSite());
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $consent->getValue());
    }

    public function testHttpOnlyFlagFollowsConstructor(): void
    {
        $handler  = new CookieHandler(false, new SystemClock());
        $response = new Response();
        $handler->save(['analytics' => true], 'key', $response);

        $cookies = $response->headers->getCookies();
        self::assertNotEmpty($cookies);
        self::assertFalse($cookies[0]->isHttpOnly());
        self::assertTrue($cookies[0]->isSecure());
    }
}
