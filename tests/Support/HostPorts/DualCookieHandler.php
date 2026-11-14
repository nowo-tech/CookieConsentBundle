<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\HostPorts;

use DateTimeImmutable;
use LogicException;
use Nowo\CookieConsentBundle\Cookie\ConsentCookie;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieJar;
use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\ConsentRequestCookies;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A host handler migrating to 1.30: implements both ports; the bundle must call the new one.
 */
final class DualCookieHandler implements CookieHandlerInterface, ConsentCookieWriterInterface
{
    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
    {
        throw new LogicException('The deprecated port must not be called.');
    }

    public function clear(Request $request, Response $response): void
    {
        throw new LogicException('The deprecated port must not be called.');
    }

    public function consentCookies(array $categories, string $key, array $granularCookies = []): ConsentCookieJar
    {
        return (new ConsentCookieJar())->with(ConsentCookie::persistent('Dual_Key', $key, new DateTimeImmutable('+1 day'), true));
    }

    public function expiredConsentCookies(ConsentRequestCookies $requestCookies): ConsentCookieJar
    {
        return new ConsentCookieJar();
    }
}
