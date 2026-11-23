<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function mb_strlen;
use function preg_match;
use function sprintf;
use function strlen;
use function trim;

/**
 * Validation shared by the provisioning value objects (1.36). Stateless.
 *
 * @internal
 */
final class SpecAssert
{
    /** Largest text a TEXT column stores on every supported server (MySQL / MariaDB TEXT: 65,535 bytes). */
    public const TEXT_MAX_BYTES = 65535;

    /**
     * A natural key (cookie name, locale): exact-key semantics (REQ-SEC-006), so no leading or trailing whitespace and
     * no control character (a PAD SPACE collation would treat "nid " as "nid").
     */
    public static function key(string $field, string $value, int $maxLength): string
    {
        if ($value === '' || trim($value) !== $value || preg_match('/[\x00-\x1F\x7F]/u', $value) !== 0) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be a non-empty string without leading or trailing whitespace or control characters.', $field));
        }

        return self::length($field, $value, $maxLength);
    }

    /**
     * A locale code (BCP 47 style, at most 10 characters: "en", "es", "pt_BR", "zh-Hant").
     */
    public static function locale(string $field, string $value): string
    {
        if (preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/D', $value) !== 1 || strlen($value) > 10) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be a locale code such as "en" or "pt_BR" (at most 10 characters), got "%s".', $field, $value));
        }

        return $value;
    }

    /**
     * A text that fits its column (`$maxLength` characters, or a TEXT column when null).
     */
    public static function length(string $field, string $value, ?int $maxLength): string
    {
        if ($maxLength === null) {
            if (strlen($value) > self::TEXT_MAX_BYTES) {
                throw new InvalidProvisioningSpecException(sprintf('%s is longer than %d bytes.', $field, self::TEXT_MAX_BYTES));
            }

            return $value;
        }

        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new InvalidProvisioningSpecException(sprintf('%s is longer than %d characters.', $field, $maxLength));
        }

        return $value;
    }

    /**
     * One of the allowed values.
     *
     * @param list<string> $allowed
     */
    public static function choice(string $field, string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be one of "%s", got "%s".', $field, implode('", "', $allowed), $value));
        }

        return $value;
    }

    /**
     * A string from a decoded fixture.
     */
    public static function string(string $field, mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be a string, got %s.', $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * A string or null from a decoded fixture.
     */
    public static function nullableString(string $field, mixed $value): ?string
    {
        return $value === null ? null : self::string($field, $value);
    }

    /**
     * A boolean from a decoded fixture (no "1" / "yes" coercion).
     */
    public static function bool(string $field, mixed $value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be a boolean, got %s.', $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * An integer from a decoded fixture.
     */
    public static function int(string $field, mixed $value): int
    {
        if (!is_int($value)) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be an integer, got %s.', $field, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * A map (string keys) from a decoded fixture.
     *
     * @return array<string, mixed>
     */
    public static function map(string $field, mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidProvisioningSpecException(sprintf('%s must be an object (a map), got %s.', $field, get_debug_type($value)));
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new InvalidProvisioningSpecException(sprintf('%s must be an object (a map) with string keys.', $field));
            }
            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * Rejects the keys of a decoded fixture that the value object does not know.
     *
     * @param array<string, mixed> $data
     * @param list<string> $known
     */
    public static function knownKeys(string $what, array $data, array $known): void
    {
        foreach ($data as $key => $unused) {
            if (!in_array($key, $known, true)) {
                throw new InvalidProvisioningSpecException(sprintf('Unknown key "%s" in %s (known: "%s").', $key, $what, implode('", "', $known)));
            }
        }
    }
}
