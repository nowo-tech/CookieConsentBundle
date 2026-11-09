<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Doctrine;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Type;
use Exception;

use function is_string;

/**
 * `DATETIME` column that always holds UTC wall-clock time, whatever PHP's default time zone is (1.25, audit JR-L2).
 *
 * Doctrine's `datetime_immutable` writes and reads the wall clock in the default zone, so a UTC value that falls into a
 * local DST gap (e.g. 02:30 on the last Sunday of March in Europe/Madrid) is shifted by an hour on hydration. This type
 * converts to UTC before writing and reads the stored string as UTC. The SQL declaration is the plain `DATETIME` of
 * `datetime_immutable`.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    public const NAME = 'nowo_utc_datetime_immutable';

    /**
     * Registers the type once (bundle boot, or tests that build an entity manager by hand).
     */
    public static function register(): void
    {
        if (!Type::hasType(self::NAME)) {
            Type::addType(self::NAME, self::class);
        }
    }

    /**
     * Returns the type name (DBAL 3; DBAL 4 uses the registry name).
     *
     * @return string The type name
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Converts the PHP value to the UTC wall-clock string stored in the column.
     *
     * @param mixed $value The PHP value
     * @param AbstractPlatform $platform The database platform
     *
     * @return string|null The database value
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTimeInterface) {
            $value = DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('UTC'));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * Reads the stored wall-clock string as UTC.
     *
     * @param mixed $value The database value
     * @param AbstractPlatform $platform The database platform
     *
     * @return DateTimeImmutable|null The UTC instant
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value instanceof DateTimeImmutable ? $value->setTimezone(new DateTimeZone('UTC')) : null;
        }

        if (!is_string($value)) {
            return parent::convertToPHPValue($value, $platform);
        }

        $utc    = new DateTimeZone('UTC');
        $parsed = DateTimeImmutable::createFromFormat('!' . $platform->getDateTimeFormatString(), $value, $utc);

        if ($parsed === false) {
            try {
                $parsed = new DateTimeImmutable($value, $utc);
            } catch (Exception) {
                // Not a date at all: let DBAL raise its own conversion error.
                return parent::convertToPHPValue($value, $platform);
            }
        }

        return $parsed;
    }
}
