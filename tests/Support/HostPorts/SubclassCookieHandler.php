<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\HostPorts;

use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * A 1.29 host subclass of the bundle handler that overrides `saveCookie()` (CookieHandler is final only by PHPDoc
 * until 2.0).
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class SubclassCookieHandler extends CookieHandler
{
    protected function saveCookie(string $name, string $value, Response $response): void
    {
        parent::saveCookie($name, $value, $response);
        $response->headers->setCookie(Cookie::create('Sub_' . $name, '1'));
    }
}
