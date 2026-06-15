<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use PHPUnit\Framework\TestCase;

final class CookieConsentLogTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $timestamp = new DateTimeImmutable('2026-01-01 12:00:00');
        $log       = (new CookieConsentLog())
            ->setIpAddress('203.0.113.1xx')
            ->setCookieConsentKey('key-1')
            ->setCookieName('analytics')
            ->setCookieValue(true)
            ->setTimestamp($timestamp);

        self::assertNull($log->getId());
        self::assertSame('203.0.113.1xx', $log->getIpAddress());
        self::assertSame('key-1', $log->getCookieConsentKey());
        self::assertSame('analytics', $log->getCookieName());
        self::assertTrue($log->getCookieValue());
        self::assertSame($timestamp, $log->getTimestamp());
    }
}
