<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\PersonalData;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class CookieConsentIpWindowTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ips(): iterable
    {
        yield 'raw IPv4' => ['203.0.113.10', '203.0.113.xx'];
        yield 'raw IPv4, one digit' => ['203.0.113.7', '203.0.113.x'];
        yield 'anonymised IPv4' => ['203.0.113.xxx', '203.0.113.xxx'];
        yield 'raw IPv6' => ['2001:db8:85a3:8d3:1319:8a2e:370:7348', '2001:db8:85a3::'];
        yield 'IPv4-mapped IPv6' => ['::ffff:192.0.2.128', '::ffff:192.0.2.xxx'];
        yield 'anonymised IPv4-mapped' => ['::ffff:192.0.2.xxx', '::ffff:192.0.2.xxx'];
    }

    #[DataProvider('ips')]
    public function testKeepsTheStoredForm(string $given, string $stored): void
    {
        $window = CookieConsentIpWindow::of($given, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'));

        self::assertSame($stored, $window->ip);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIps(): iterable
    {
        yield 'empty' => [''];
        yield 'nul byte' => ["203.0.113.x\0"];
        yield 'too long' => [str_repeat('1', 256)];
        yield 'unknown' => ['unknown'];
        yield 'host name' => ['example.test'];
        yield 'upper-case mask' => ['203.0.113.XX'];
        yield 'padded' => [' 203.0.113.10'];
    }

    #[DataProvider('invalidIps')]
    public function testRejectsInvalidIps(string $ip): void
    {
        $this->expectException(InvalidArgumentException::class);
        CookieConsentIpWindow::of($ip, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-02'));
    }

    public function testRejectsAWindowThatEndsBeforeItStarts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CookieConsentIpWindow::of('203.0.113.10', new DateTimeImmutable('2026-01-02'), new DateTimeImmutable('2026-01-01'));
    }

    public function testWidenContainsAndToArray(): void
    {
        $window = CookieConsentIpWindow::of('203.0.113.10', new DateTimeImmutable('2026-01-10T00:00:00+00:00'), new DateTimeImmutable('2026-01-11T00:00:00+00:00'))
            ->widen(new DateInterval('P1D'));

        self::assertSame(['ip' => '203.0.113.xx', 'from' => '2026-01-09T00:00:00+00:00', 'to' => '2026-01-12T00:00:00+00:00'], $window->toArray());
        self::assertTrue($window->contains('203.0.113.xx', new DateTimeImmutable('2026-01-09T00:00:00+00:00')));
        self::assertTrue($window->contains('203.0.113.xx', new DateTimeImmutable('2026-01-12T00:00:00+00:00')));
        self::assertFalse($window->contains('203.0.113.xx', new DateTimeImmutable('2026-01-12T00:00:01+00:00')));
        self::assertFalse($window->contains('203.0.113.XX', new DateTimeImmutable('2026-01-10T00:00:00+00:00')));
        self::assertFalse($window->contains('203.0.113.x', new DateTimeImmutable('2026-01-10T00:00:00+00:00')));
    }
}
