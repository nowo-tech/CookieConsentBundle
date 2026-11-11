<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Http;

use Nowo\CookieConsentBundle\Cookie\ConsentCookie;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_keys;
use function array_map;

/**
 * HTTP edge of the framework-free consent cookie port (1.30, REQ-ARCH-001 rule 3).
 *
 * Reads the request cookie names into a {@see ConsentRequestCookies} and writes a {@see ConsentCookieJar} on the
 * response with exactly the `Set-Cookie` attributes the deprecated `CookieHandlerInterface` methods used. Stateless.
 */
final class ConsentCookieJarApplier
{
    /**
     * Returns the cookie names the request carries.
     */
    public static function requestCookies(Request $request): ConsentRequestCookies
    {
        return new ConsentRequestCookies(array_map(strval(...), array_keys($request->cookies->all())));
    }

    /**
     * Writes every cookie of the jar on the response.
     */
    public static function apply(ConsentCookieJar $jar, Response $response): void
    {
        foreach ($jar->cookies() as $cookie) {
            self::applyOne($cookie, $response);
        }
    }

    private static function applyOne(ConsentCookie $cookie, Response $response): void
    {
        if ($cookie->expired) {
            $response->headers->clearCookie(
                $cookie->name,
                $cookie->path,
                $cookie->domain,
                $cookie->secure,
                $cookie->httpOnly,
                $cookie->sameSite,
            );

            return;
        }

        $response->headers->setCookie(
            new Cookie(
                $cookie->name,
                $cookie->value,
                $cookie->expiresAt ?? 0,
                $cookie->path,
                $cookie->domain,
                $cookie->secure,
                $cookie->httpOnly,
                false,
                $cookie->sameSite,
            ),
        );
    }
}
