<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use function bin2hex;
use function is_string;
use function preg_match;
use function random_bytes;

/**
 * Format of the anonymous consent key (`Cookie_Consent_Key`) that identifies a visitor's consent log rows (1.30.2,
 * REQ-SEC-006 item 1).
 *
 * The bundle writes 32 lower-case hexadecimal characters (`bin2hex(random_bytes(16))`, since 1.0.x); 1.0.0 wrote
 * `uniqid('', true)` (13 hexadecimal characters, one digit, a dot and eight digits). A cookie in any other shape (other
 * letter case, spaces, control or format characters, another length) is refused and the visitor gets a new key, so a
 * look-alike of another visitor's key never reaches the log. Stateless.
 */
final class ConsentKey
{
    /** Keys the bundle has ever written, anchored with `\A…\z` and `/D`. */
    public const PATTERN = '/\A(?:[0-9a-f]{32}|[0-9a-f]{13}[0-9]\.[0-9]{8})\z/D';

    /**
     * Whether `$value` is a consent key the bundle could have written.
     *
     * @phpstan-assert-if-true string $value
     */
    public static function isWellFormed(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * A new unguessable, non time-based key.
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * The key of the request cookie when well-formed, else a new one.
     */
    public static function fromCookie(mixed $cookie): string
    {
        return self::isWellFormed($cookie) ? $cookie : self::generate();
    }
}
