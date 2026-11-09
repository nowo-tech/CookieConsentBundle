<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use DateInterval;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_keys;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Writes cookie consent and category cookies onto the HTTP response.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieHandler
{
    /**
     * Creates a new cookie handler.
     *
     * @param bool $httpOnly Whether consent cookies should be HttpOnly
     * @param ClockInterface $clock Clock used for cookie expiration
     */
    public function __construct(
        private readonly bool $httpOnly,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Saves consent and category cookies on the response.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     * @param Response $response The HTTP response to modify
     * @param array<string, bool> $granularCookies Optional per-cookie consent map
     */
    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
    {
        // UTC ISO-8601 (stable across locales); not RFC 2822 `date('r')`.
        $this->saveCookie(CookieNameEnum::COOKIE_CONSENT_NAME, gmdate('Y-m-d\TH:i:s\Z'), $response);
        $this->saveCookie(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, $key, $response);

        foreach ($categories as $category => $permitted) {
            if ($category === 'required' || $category === 'cookies') {
                continue;
            }

            $stringValue = $permitted === true || $permitted === 'true' ? 'true' : 'false';
            $this->saveCookie(CookieNameEnum::getCookieCategoryName((string) $category), $stringValue, $response);
        }

        if ($granularCookies !== []) {
            $encoded = json_encode($this->normalizeGranularCookies($granularCookies), JSON_THROW_ON_ERROR);
            $this->saveCookie(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, $encoded, $response);
        }
    }

    /**
     * Expires the consent cookies the request carries (1.25, JH-L1: an outdated consent).
     *
     * Removes `Cookie_Consent`, `Cookie_Consent_Granular` and every `Cookie_Category_*` cookie, so scripts that read
     * the category cookies themselves see no consent either. `Cookie_Consent_Key` is kept: it links the visitor's
     * next answer to the same consent log rows.
     *
     * @param Request $request The request whose cookies are expired
     * @param Response $response The response that receives the expired cookies
     */
    public function clear(Request $request, Response $response): void
    {
        foreach (array_keys($request->cookies->all()) as $name) {
            $name = (string) $name;

            if ($name !== CookieNameEnum::COOKIE_CONSENT_NAME
                && $name !== CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME
                && !str_starts_with($name, CookieNameEnum::COOKIE_CATEGORY_NAME_PREFIX)) {
                continue;
            }

            $response->headers->clearCookie($name, '/', null, true, $this->httpOnly, Cookie::SAMESITE_LAX);
        }
    }

    /**
     * @param array<string, bool> $granularCookies
     *
     * @return array<string, bool>
     */
    private function normalizeGranularCookies(array $granularCookies): array
    {
        $normalized = [];

        foreach ($granularCookies as $cookieName => $allowed) {
            if ($cookieName === '') {
                continue;
            }

            $normalized[$cookieName] = $allowed;
        }

        return $normalized;
    }

    protected function saveCookie(string $name, string $value, Response $response): void
    {
        $expirationDate = $this->clock->now()->add(new DateInterval('P1Y'));

        // Always Secure + SameSite=Lax: TLS is usually terminated at a reverse proxy
        // (Traefik/Caddy), so Request::isSecure() is false inside FrankenPHP/PHP-FPM and
        // Symfony's secure=null ("auto") would emit cookies without the Secure flag.
        // Browsers on https:// then drop them and the consent banner reappears on refresh.
        $response->headers->setCookie(
            new Cookie(
                $name,
                $value,
                $expirationDate,
                '/',
                null,
                true,
                $this->httpOnly,
                false,
                Cookie::SAMESITE_LAX,
            ),
        );
    }
}
