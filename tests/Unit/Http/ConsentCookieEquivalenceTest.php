<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Http\ConsentCookieJarApplier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function preg_replace;

/**
 * 1.30 (REQ-ARCH-001 rule 3): the framework-free port writes byte-identical `Set-Cookie` headers to the deprecated
 * `CookieHandlerInterface` methods (Secure, HttpOnly, SameSite, path, domain, expiry, raw, order).
 */
final class ConsentCookieEquivalenceTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, array<string, bool|string>, array<string, bool>}>
     */
    public static function saves(): iterable
    {
        yield 'accept all, http only' => [true, ['required' => true, 'analytics' => true, 'marketing' => 'true'], []];
        yield 'reject all, not http only' => [false, ['required' => true, 'analytics' => false, 'marketing' => 'false'], []];
        yield 'granular' => [true, ['required' => true, 'analytics' => true, 'cookies' => 'ignored'], ['_ga' => true, '_fbp' => false, '' => true]];
        yield 'no categories' => [false, [], []];
    }

    /**
     * @param array<string, bool|string> $categories
     * @param array<string, bool> $granular
     */
    #[DataProvider('saves')]
    public function testSaveIsByteIdentical(bool $httpOnly, array $categories, array $granular): void
    {
        $handler = new CookieHandler($httpOnly, $this->fixedClock());

        $legacy = new Response();
        $handler->save($categories, 'consent-key-1', $legacy, $granular);

        $twin = new Response();
        ConsentCookieJarApplier::apply($handler->consentCookies($categories, 'consent-key-1', $granular), $twin);

        self::assertSame($this->headers($legacy), $this->headers($twin));
        self::assertSame($this->attributes($legacy), $this->attributes($twin));
    }

    /**
     * @return iterable<string, array{bool, array<string, string>}>
     */
    public static function clears(): iterable
    {
        yield 'outdated consent with granular' => [true, [
            CookieNameEnum::COOKIE_CONSENT_NAME          => '2026-01-01T00:00:00Z',
            CookieNameEnum::COOKIE_CONSENT_KEY_NAME      => 'k',
            CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME => '{}',
            'Cookie_Category_analytics'                  => 'true',
            'PHPSESSID'                                  => 'abc',
        ]];
        yield 'not http only, category only' => [false, ['Cookie_Category_marketing' => 'false', 'other' => '1']];
        yield 'nothing to expire' => [true, ['PHPSESSID' => 'abc']];
    }

    /**
     * @param array<string, string> $cookies
     */
    #[DataProvider('clears')]
    public function testClearIsByteIdentical(bool $httpOnly, array $cookies): void
    {
        $handler = new CookieHandler($httpOnly, new SystemClock());
        $request = Request::create('/', 'GET', [], $cookies);

        $legacy = new Response();
        $handler->clear($request, $legacy);

        $twin = new Response();
        ConsentCookieJarApplier::apply($handler->expiredConsentCookies(ConsentCookieJarApplier::requestCookies($request)), $twin);

        self::assertSame($this->headers($legacy), $this->headers($twin));
        self::assertSame($this->attributes($legacy), $this->attributes($twin));
    }

    public function testSetCookieHeaderKeepsEveryAttribute(): void
    {
        $handler = new CookieHandler(true, $this->fixedClock());
        $twin    = new Response();
        ConsentCookieJarApplier::apply($handler->consentCookies(['analytics' => true], 'consent-key-1'), $twin);

        $headers = $this->headers($twin);
        self::assertContains(
            'Cookie_Consent_Key=consent-key-1; expires=Mon, 01 Mar 2100 10:00:00 GMT; Max-Age=X; path=/; secure; httponly; samesite=lax',
            $headers,
        );
        self::assertContains(
            'Cookie_Category_analytics=true; expires=Mon, 01 Mar 2100 10:00:00 GMT; Max-Age=X; path=/; secure; httponly; samesite=lax',
            $headers,
        );

        $cleared = new Response();
        ConsentCookieJarApplier::apply(
            $handler->expiredConsentCookies(ConsentCookieJarApplier::requestCookies(Request::create('/', 'GET', [], ['Cookie_Consent' => 'x']))),
            $cleared,
        );
        $lines = $this->headers($cleared);
        self::assertCount(1, $lines);
        // Symfony's clearCookie(): value "deleted", expiry one year ago, same path / Secure / HttpOnly / SameSite.
        self::assertMatchesRegularExpression(
            '/^Cookie_Consent=deleted; expires=[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT; Max-Age=X; path=\/; secure; httponly; samesite=lax$/',
            $lines[0],
        );
    }

    private function fixedClock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2099-03-01 10:00:00', new DateTimeZone('UTC'));
            }
        };
    }

    /**
     * The `Set-Cookie` lines with the `Max-Age` (seconds from now, computed when the header is printed) masked, and
     * the `Cookie_Consent` value (`gmdate()` of the answer, may cross a second between the two calls) masked.
     *
     * @return list<string>
     */
    private function headers(Response $response): array
    {
        $lines = [];

        foreach ($response->headers->all('set-cookie') as $line) {
            $line    = (string) preg_replace('/Max-Age=\d+/', 'Max-Age=X', (string) $line);
            $lines[] = (string) preg_replace('/^Cookie_Consent=\d{4}-\d{2}-\d{2}T\d{2}%3A\d{2}%3A\d{2}Z;/', 'Cookie_Consent=TIME;', $line);
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attributes(Response $response): array
    {
        return array_values(array_map(static fn (Cookie $cookie): array => [
            'name'        => $cookie->getName(),
            'expires'     => $cookie->getExpiresTime(),
            'path'        => $cookie->getPath(),
            'domain'      => $cookie->getDomain(),
            'secure'      => $cookie->isSecure(),
            'httpOnly'    => $cookie->isHttpOnly(),
            'raw'         => $cookie->isRaw(),
            'sameSite'    => $cookie->getSameSite(),
            'partitioned' => $cookie->isPartitioned(),
            'cleared'     => $cookie->isCleared(),
        ], $response->headers->getCookies()));
    }
}
