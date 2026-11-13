<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use Nowo\CookieConsentBundle\Cookie\ConsentKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function uniqid;

/**
 * REQ-SEC-006 item 1: the consent key cookie has a strict format; anything else is replaced by a new key.
 */
final class ConsentKeyTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformed(): iterable
    {
        yield 'missing' => [null];
        yield 'not a string' => [['k']];
        yield 'empty' => [''];
        yield 'upper case' => ['0123456789ABCDEF0123456789ABCDEF'];
        yield 'trailing space' => [self::KEY . ' '];
        yield 'leading space' => [' ' . self::KEY];
        yield 'trailing newline' => [self::KEY . "\n"];
        yield 'NBSP' => [self::KEY . "\u{00A0}"];
        yield 'ZWSP' => ["0123456789abcdef\u{200B}0123456789abcdef"];
        yield 'accented look-alike' => ["0123456789abcd\u{00E9}f0123456789abcdef"];
        yield 'too short' => ['0123456789abcdef'];
        yield 'too long' => [self::KEY . 'a'];
        yield 'free text' => ['visitor-key'];
        yield 'legacy uniqid with a newline' => ["64f9c8a5b2e1d1.23456789\n"];
    }

    #[DataProvider('malformed')]
    public function testMalformedCookiesAreReplaced(mixed $cookie): void
    {
        self::assertFalse(ConsentKey::isWellFormed($cookie));
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/D', ConsentKey::fromCookie($cookie));
    }

    public function testKeysTheBundleWritesAreKept(): void
    {
        self::assertSame(self::KEY, ConsentKey::fromCookie(self::KEY));
        $legacy = uniqid('', true); // 1.0.0
        self::assertSame($legacy, ConsentKey::fromCookie($legacy));
        $new = ConsentKey::generate();
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/D', $new);
        self::assertNotSame($new, ConsentKey::generate());
    }
}
