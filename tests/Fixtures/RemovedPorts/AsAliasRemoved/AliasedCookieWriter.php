<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Fixtures\RemovedPorts\AsAliasRemoved;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * A loadable application writer still aliased to the id of the port removed in 2.0 (W1-1 fixture).
 */
#[AsAlias('Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface')]
final class AliasedCookieWriter implements ConsentCookieWriterInterface
{
    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar
    {
        return new ConsentCookieJar();
    }

    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar
    {
        return new ConsentCookieJar();
    }
}
