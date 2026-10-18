<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use DateInterval;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

/**
 * Writes cookie consent and category cookies onto the HTTP response.
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
