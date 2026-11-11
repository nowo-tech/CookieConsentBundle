<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\HostPorts;

use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A 1.29 host decorator (`#[AsDecorator(CookieHandler::class)]` style): implements only the deprecated port and adds
 * one cookie after the inner handler.
 */
final class LegacyDecoratingCookieHandler implements CookieHandlerInterface
{
    public function __construct(
        private readonly CookieHandlerInterface $inner,
    ) {
    }

    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
    {
        $this->inner->save($categories, $key, $response, $granularCookies);
        $response->headers->setCookie(Cookie::create('Decorated', '1'));
    }

    public function clear(Request $request, Response $response): void
    {
        $this->inner->clear($request, $response);
    }
}
