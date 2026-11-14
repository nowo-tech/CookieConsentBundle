<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\HostPorts;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Cookie\ConsentCookie;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;

/**
 * A DDD host's consent cookie writer: implements only the framework-free port (no Request, no Response) and keeps no
 * visitor state; `$calls` only counts calls for the tests.
 */
final class HostConsentCookieWriter implements ConsentCookieWriterInterface
{
    public int $calls = 0;

    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar
    {
        ++$this->calls;
        $allowed = $categories['analytics'] ?? false;

        return (new ConsentCookieJar())
            ->with(ConsentCookie::persistent('Host_Consent_Key', $key, new DateTimeImmutable('+30 days'), true))
            ->with(ConsentCookie::persistent('Host_Analytics', $allowed === true || $allowed === 'true' ? 'yes' : 'no', new DateTimeImmutable('+30 days'), true));
    }

    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar
    {
        ++$this->calls;

        return $requestCookies->has('Host_Analytics')
            ? (new ConsentCookieJar())->with(ConsentCookie::expired('Host_Analytics', true))
            : new ConsentCookieJar();
    }
}
