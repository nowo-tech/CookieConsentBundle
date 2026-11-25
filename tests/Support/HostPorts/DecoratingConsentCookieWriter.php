<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\HostPorts;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Cookie\ConsentCookie;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;

/**
 * A host decorator of the consent cookie port (`nowo_cookie_consent.cookie_writer`, or a replaced `CookieHandler` in
 * the 2.0 build-error case): adds one cookie after the inner writer's.
 */
final class DecoratingConsentCookieWriter implements ConsentCookieWriterInterface
{
    public function __construct(
        private readonly ConsentCookieWriterInterface $inner,
    ) {
    }

    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar
    {
        return $this->inner->consentCookies($categories, $key, $granularCookies)
            ->with(ConsentCookie::persistent('Decorated', '1', new DateTimeImmutable('+1 day'), true));
    }

    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar
    {
        return $this->inner->expiredConsentCookies($requestCookies);
    }
}
