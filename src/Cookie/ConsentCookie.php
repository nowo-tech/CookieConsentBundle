<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One consent cookie to write or to expire, without any HTTP type (1.30, REQ-ARCH-001 rule 3).
 *
 * The attributes are the ones the bundle has always used: path `/`, no domain, `Secure`, `SameSite=Lax`, not raw;
 * `HttpOnly` follows `nowo_cookie_consent.http_only`. The HTTP edge (`Http\ConsentCookieJarApplier`)
 * turns it into a `Set-Cookie` header.
 */
final readonly class ConsentCookie
{
    public const PATH = '/';

    public const SAME_SITE_LAX = 'lax';

    /**
     * @param string $name The cookie name
     * @param string $value The cookie value (empty for an expired cookie)
     * @param DateTimeImmutable|null $expiresAt When the cookie expires (null for an expired cookie)
     * @param bool $httpOnly Whether the cookie is HttpOnly
     * @param bool $expired True to expire (delete) the cookie in the browser
     * @param string $path The cookie path
     * @param string|null $domain The cookie domain (null = host only)
     * @param bool $secure Whether the cookie is Secure
     * @param 'lax'|'none'|'strict' $sameSite The SameSite attribute
     */
    private function __construct(
        public string $name,
        public string $value,
        public ?DateTimeImmutable $expiresAt,
        public bool $httpOnly,
        public bool $expired,
        public string $path = self::PATH,
        public ?string $domain = null,
        public bool $secure = true,
        public string $sameSite = self::SAME_SITE_LAX,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('A consent cookie needs a name.');
        }
    }

    /**
     * A cookie the browser keeps until `$expiresAt`.
     */
    public static function persistent(string $name, string $value, DateTimeImmutable $expiresAt, bool $httpOnly): self
    {
        return new self($name, $value, $expiresAt, $httpOnly, false);
    }

    /**
     * A cookie the browser must delete (same path, domain, Secure, HttpOnly and SameSite as when it was set).
     */
    public static function expired(string $name, bool $httpOnly): self
    {
        return new self($name, '', null, $httpOnly, true);
    }
}
