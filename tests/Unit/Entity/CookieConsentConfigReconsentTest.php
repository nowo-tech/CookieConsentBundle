<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function date_default_timezone_get;
use function date_default_timezone_set;

use const DATE_ATOM;

final class CookieConsentConfigReconsentTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        // @phpstan-ignore frankenphp.worker.noDateDefaultTimezoneSet (test restores the zone in tearDown)
        date_default_timezone_set($this->timezone);
    }

    public function testNeverRequestedByDefault(): void
    {
        $config = new CookieConsentConfig();

        self::assertNull($config->getReconsentRequiredAt());
        self::assertFalse($config->isConsentOutdated(new DateTimeImmutable('2000-01-01')));
        self::assertFalse($config->isConsentOutdated(null));
    }

    public function testStoredAsUtcWallClockWhateverThePhpZone(): void
    {
        // @phpstan-ignore frankenphp.worker.noDateDefaultTimezoneSet (test restores the zone in tearDown)
        date_default_timezone_set('Europe/Madrid');
        $config = (new CookieConsentConfig())->requireReconsent(new DateTimeImmutable('2026-07-01T12:00:00+02:00'));

        $stored = (new ReflectionProperty(CookieConsentConfig::class, 'reconsentRequiredAt'))->getValue($config);
        self::assertInstanceOf(DateTimeImmutable::class, $stored);
        self::assertSame('2026-07-01 10:00:00', $stored->format('Y-m-d H:i:s'), 'column value (what Doctrine writes)');

        // Hydrated later under another default zone: still read as UTC.
        // @phpstan-ignore frankenphp.worker.noDateDefaultTimezoneSet (test restores the zone in tearDown)
        date_default_timezone_set('America/New_York');
        $requiredAt = $config->getReconsentRequiredAt();
        self::assertNotNull($requiredAt);
        self::assertSame('2026-07-01T10:00:00+00:00', $requiredAt->format(DATE_ATOM));
    }

    public function testComparisonIsBySecond(): void
    {
        $config = (new CookieConsentConfig())->requireReconsent(new DateTimeImmutable('2026-11-09T10:00:00.900Z'));

        self::assertTrue($config->isConsentOutdated(new DateTimeImmutable('2026-11-09T09:59:59Z')));
        self::assertFalse($config->isConsentOutdated(new DateTimeImmutable('2026-11-09T10:00:00Z')));
        self::assertTrue($config->isConsentOutdated(null), 'unknown consent time once re-consent was requested');
    }

    public function testClearing(): void
    {
        $config = (new CookieConsentConfig())->requireReconsent(new DateTimeImmutable());
        $config->setReconsentRequiredAt(null);

        self::assertNull($config->getReconsentRequiredAt());
    }
}
