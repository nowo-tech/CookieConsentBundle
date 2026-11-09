<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Nowo\CookieConsentBundle\Doctrine\UtcDateTimeImmutableType;
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
        self::assertSame('2026-07-01 10:00:00 UTC', $stored->format('Y-m-d H:i:s T'), 'held in UTC');

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

    /**
     * Audit JR-L2: 02:30 UTC on 2026-03-29 is inside Europe/Madrid's DST gap (02:00-03:00 local). Neither the setter
     * nor the column type may pass the wall clock through the default zone.
     */
    public function testDstGapOfTheDefaultZoneDoesNotShiftTheValue(): void
    {
        // @phpstan-ignore frankenphp.worker.noDateDefaultTimezoneSet (test restores the zone in tearDown)
        date_default_timezone_set('Europe/Madrid');
        UtcDateTimeImmutableType::register();
        $type     = Type::getType(UtcDateTimeImmutableType::NAME);
        $platform = new SQLitePlatform();

        $config = (new CookieConsentConfig())->requireReconsent(new DateTimeImmutable('2026-03-29T02:30:00Z'));
        self::assertSame('2026-03-29T02:30:00+00:00', $config->getReconsentRequiredAt()?->format(DATE_ATOM));

        $stored = $type->convertToDatabaseValue((new ReflectionProperty(CookieConsentConfig::class, 'reconsentRequiredAt'))->getValue($config), $platform);
        self::assertSame('2026-03-29 02:30:00', $stored);

        $hydrated = $type->convertToPHPValue($stored, $platform);
        self::assertInstanceOf(DateTimeImmutable::class, $hydrated);
        self::assertSame('2026-03-29T02:30:00+00:00', $hydrated->format(DATE_ATOM));

        (new ReflectionProperty(CookieConsentConfig::class, 'reconsentRequiredAt'))->setValue($config, $hydrated);
        self::assertTrue($config->isConsentOutdated(new DateTimeImmutable('2026-03-29T02:29:59Z')));
        self::assertFalse($config->isConsentOutdated(new DateTimeImmutable('2026-03-29T02:30:00Z')), 'a fresh answer counts at once');

        self::assertSame('2026-03-29 02:30:00', $type->convertToDatabaseValue(new DateTimeImmutable('2026-03-29T04:30:00+02:00'), $platform), 'other zones are converted to UTC');
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertSame(UtcDateTimeImmutableType::NAME, (new UtcDateTimeImmutableType())->getName());
        self::assertSame('2026-03-29T02:30:00+00:00', $type->convertToPHPValue('2026-03-29T02:30:00', $platform)?->format(DATE_ATOM), 'other formats read as UTC');
        self::assertSame('2026-03-29T02:30:00+00:00', $type->convertToPHPValue(new DateTimeImmutable('2026-03-29T04:30:00+02:00'), $platform)?->format(DATE_ATOM));

        $this->expectException(ConversionException::class);
        $type->convertToPHPValue('not a date', $platform);
    }
}
