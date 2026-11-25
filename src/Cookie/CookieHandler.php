<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use DateInterval;
use DateTimeImmutable;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Psr\Clock\ClockInterface;

use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Writes cookie consent and category cookies onto the HTTP response.
 *
 * Implements the framework-free {@see ConsentCookieWriterInterface} (1.30; the only consent cookie port since 2.0):
 * path `/`, no domain, `Secure`, `SameSite=Lax`, `HttpOnly` from `nowo_cookie_consent.http_only`, one year expiry.
 */
final class CookieHandler implements ConsentCookieWriterInterface
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
     * Returns the consent cookies of a saved consent (1.30).
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     * @param array<string, bool> $granularCookies Optional per-cookie consent map
     */
    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar
    {
        $jar = new ConsentCookieJar();

        foreach ($this->consentValues($categories, $key, $granularCookies) as $name => $value) {
            $jar = $jar->with(ConsentCookie::persistent((string) $name, $value, $this->expirationDate(), $this->httpOnly));
        }

        return $jar;
    }

    /**
     * Returns the cookies to expire for an outdated consent (1.30, JH-L1: an outdated consent; `Cookie_Consent_Key` is kept).
     *
     * @param ConsentRequestCookies $requestCookies The cookie names the request carries
     */
    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar
    {
        $jar = new ConsentCookieJar();

        foreach ($this->clearableNames($requestCookies->names()) as $name) {
            $jar = $jar->with(ConsentCookie::expired($name, $this->httpOnly));
        }

        return $jar;
    }

    /**
     * The cookie values of a saved consent, by cookie name, in the order they are written.
     *
     * @param array<string, bool|string> $categories
     * @param array<string, bool> $granularCookies
     *
     * @return array<string, string>
     */
    private function consentValues(array $categories, string $key, array $granularCookies): array
    {
        // UTC ISO-8601 (stable across locales); not RFC 2822 `date('r')`.
        $values = [
            CookieNameEnum::COOKIE_CONSENT_NAME     => gmdate('Y-m-d\TH:i:s\Z'),
            CookieNameEnum::COOKIE_CONSENT_KEY_NAME => $key,
        ];

        foreach ($categories as $category => $permitted) {
            if ($category === 'required' || $category === 'cookies') {
                continue;
            }

            $values[CookieNameEnum::getCookieCategoryName((string) $category)] = $permitted === true || $permitted === 'true' ? 'true' : 'false';
        }

        if ($granularCookies !== []) {
            $values[CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME] = json_encode($this->normalizeGranularCookies($granularCookies), JSON_THROW_ON_ERROR);
        }

        return $values;
    }

    /**
     * Keeps the consent, granular and category cookie names (not `Cookie_Consent_Key`).
     *
     * @param array<int|string> $names
     *
     * @return list<string>
     */
    private function clearableNames(array $names): array
    {
        $clearable = [];

        foreach ($names as $name) {
            $name = (string) $name;

            if ($name !== CookieNameEnum::COOKIE_CONSENT_NAME
                && $name !== CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME
                && !str_starts_with($name, CookieNameEnum::COOKIE_CATEGORY_NAME_PREFIX)) {
                continue;
            }

            $clearable[] = $name;
        }

        return $clearable;
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

    private function expirationDate(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now())->add(new DateInterval('P1Y'));
    }
}
