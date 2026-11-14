<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\PersonalData;

use DateTimeImmutable;
use InvalidArgumentException;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentErasureResult;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentIpWindow;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class CookieConsentPersonalDataSubjectTest extends TestCase
{
    public function testFromConsentKeyKeepsTheKeyAsGiven(): void
    {
        $subject = CookieConsentPersonalDataSubject::fromConsentKey('Key-A');

        self::assertSame(['Key-A'], $subject->consentKeys());
        self::assertSame([], $subject->ipWindows());
        self::assertTrue($subject->matches('Key-A', 'unknown', new DateTimeImmutable()));
        // REQ-SEC-006: look-alikes of the key are other keys.
        foreach (['key-a', "K\u{00E9}y-A", 'Key-A ', "Key-A\n", "Key-A\u{00A0}", "Key-\u{200B}A"] as $lookalike) {
            self::assertFalse($subject->matches($lookalike, 'unknown', new DateTimeImmutable()), $lookalike);
        }
    }

    public function testFromIpWindow(): void
    {
        $subject = CookieConsentPersonalDataSubject::fromIpWindow('203.0.113.10', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), new DateTimeImmutable('2026-01-02T00:00:00+00:00'));

        self::assertSame(['consent_keys' => [], 'ip_windows' => [['ip' => '203.0.113.xx', 'from' => '2026-01-01T00:00:00+00:00', 'to' => '2026-01-02T00:00:00+00:00']]], $subject->toArray());
        self::assertTrue($subject->matches('any', '203.0.113.xx', new DateTimeImmutable('2026-01-01T12:00:00+00:00')));
        self::assertFalse($subject->matches('any', '203.0.113.xx', new DateTimeImmutable('2026-01-03T12:00:00+00:00')));
    }

    public function testOfDropsDuplicatesAndMergeUnites(): void
    {
        $window  = CookieConsentIpWindow::of('203.0.113.10', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
        $same    = CookieConsentIpWindow::of('203.0.113.11', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), new DateTimeImmutable('2026-01-02T00:00:00+00:00'));
        $subject = CookieConsentPersonalDataSubject::of([$window, $same], ['k1', 'k1', '123']);

        self::assertCount(1, $subject->ipWindows());
        self::assertSame(['k1', '123'], $subject->consentKeys());

        $merged = $subject->merge(CookieConsentPersonalDataSubject::fromConsentKey('k2'));
        self::assertSame(['k1', '123', 'k2'], $merged->consentKeys());
        self::assertCount(1, $merged->ipWindows());
    }

    public function testOfNeedsSomething(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CookieConsentPersonalDataSubject::of();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'nul byte' => ["key\0"];
        yield 'edge spaces' => [' Key-A '];
        yield 'trailing space' => ['key '];
        yield 'trailing newline' => ["key\n"];
        yield 'NBSP' => ["key\u{00A0}"];
        yield 'ZWSP' => ["k\u{200B}ey"];
        yield 'BOM' => ["\u{FEFF}key"];
        yield 'invalid UTF-8' => ["key\xC3"];
        yield 'too long' => [str_repeat('k', 256)];
    }

    #[DataProvider('invalidKeys')]
    public function testRejectsInvalidKeys(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        CookieConsentPersonalDataSubject::fromConsentKey($key);
    }

    public function testAcceptsAKeyOfTheColumnLength(): void
    {
        self::assertSame([str_repeat('k', 255)], CookieConsentPersonalDataSubject::fromConsentKey(str_repeat('k', 255))->consentKeys());
    }

    public function testErasureResult(): void
    {
        self::assertSame(['consent_logs' => 0], (new CookieConsentErasureResult())->toArray());
        self::assertSame(4, (new CookieConsentErasureResult(4))->total());
    }
}
