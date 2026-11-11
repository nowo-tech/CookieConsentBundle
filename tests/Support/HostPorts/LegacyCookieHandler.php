<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\HostPorts;

use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A host handler written for 1.29: implements only the deprecated Request / Response port.
 */
final class LegacyCookieHandler implements CookieHandlerInterface
{
    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
    {
        $response->headers->setCookie(Cookie::create('Legacy_Key', $key));
    }

    public function clear(Request $request, Response $response): void
    {
        $response->headers->clearCookie('Legacy_Key');
    }
}
