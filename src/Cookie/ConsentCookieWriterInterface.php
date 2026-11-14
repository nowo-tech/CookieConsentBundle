<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

/**
 * Decides which consent cookies to write or expire, without any HTTP type (1.30, REQ-ARCH-001 rule 3).
 *
 * Framework-free twin of the deprecated {@see CookieHandlerInterface}: it returns a {@see ConsentCookieJar} and the
 * bundle applies it to the response. {@see CookieHandler} is the default implementation; alias this interface to your
 * own service to change how the consent is stored in the browser.
 */
interface ConsentCookieWriterInterface
{
    /**
     * Returns the consent, key, category and (when given) granular cookies of a saved consent.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     * @param array<string, bool> $granularCookies Optional per-cookie consent map
     */
    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar;

    /**
     * Returns the cookies to expire for an outdated consent (the consent key is kept).
     *
     * @param ConsentRequestCookies $requestCookies The cookie names the request carries
     */
    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar;
}
