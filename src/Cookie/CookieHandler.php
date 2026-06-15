<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes cookie consent and category cookies onto the HTTP response.
 */
class CookieHandler
{
    /**
     * Creates a new cookie handler.
     *
     * @param bool $httpOnly Whether consent cookies should be HttpOnly
     */
    public function __construct(
        private readonly bool $httpOnly,
    ) {
    }

    /**
     * Saves consent and category cookies on the response.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     * @param Response $response The HTTP response to modify
     * @param array<string, bool> $granularCookies Optional per-cookie consent map
     *
     * @return void
     */
    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
    {
        $this->saveCookie(CookieNameEnum::COOKIE_CONSENT_NAME, date('r'), $response);
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
            if (!is_string($cookieName) || $cookieName === '') {
                continue;
            }

            $normalized[$cookieName] = $allowed === true || $allowed === 'true';
        }

        return $normalized;
    }

    protected function saveCookie(string $name, string $value, Response $response): void
    {
        $expirationDate = new \DateTime();
        $expirationDate->add(new \DateInterval('P1Y'));

        $response->headers->setCookie(
            new Cookie($name, $value, $expirationDate, '/', null, null, $this->httpOnly, true),
        );
    }
}
