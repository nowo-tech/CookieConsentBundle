<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use function filter_var;
use function inet_ntop;
use function inet_pton;
use function str_contains;
use function str_repeat;
use function strlen;
use function strrpos;
use function substr;

use const FILTER_VALIDATE_IP;

/**
 * The anonymisation applied to the client IP before a consent row is stored (1.29): IPv4 masks the last octet with one
 * `x` per digit, IPv6 keeps only the first 48 bits (the rest is zeroed). Shared by {@see CookieLogger} and the
 * personal-data ports, which compare a person's IP with the stored form.
 *
 * @internal
 */
final class ConsentIpAnonymizer
{
    /** Stored when the request has no valid client IP. */
    public const UNKNOWN = 'unknown';

    /**
     * Returns the stored form of a client IP, or {@see self::UNKNOWN} when it is not a valid IP.
     */
    public static function anonymize(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return self::UNKNOWN;
        }

        if (!str_contains($ip, '.')) {
            return (string) inet_ntop(substr((string) inet_pton($ip), 0, 6) . str_repeat("\0", 10));
        }

        // IPv4 or IPv4-mapped IPv6 (e.g. ::ffff:192.0.2.1): mask the last octet.
        $lastDot = (int) strrpos($ip, '.') + 1;

        return substr($ip, 0, $lastDot) . str_repeat('x', strlen($ip) - $lastDot);
    }
}
