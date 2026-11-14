<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes and expires the consent cookies on the response (1.26, DDD P2).
 *
 * {@see CookieHandler} is the default implementation; alias this interface to your own service to change how the
 * consent is stored in the browser.
 *
 * @deprecated since 1.30, implement the framework-free {@see ConsentCookieWriterInterface} instead (REQ-ARCH-001 rule 3:
 *             no `Request` / `Response` in public ports); removed in 2.0. The bundle still calls a service that
 *             implements only this interface, and triggers a deprecation when it does.
 */
interface CookieHandlerInterface
{
    /**
     * Saves the consent and category cookies on the response.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     * @param Response $response The HTTP response to modify
     * @param array<string, bool> $granularCookies Optional per-cookie consent map
     */
    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void;

    /**
     * Expires the consent cookies the request carries (an outdated consent); the consent key is kept.
     *
     * @param Request $request The request whose cookies are expired
     * @param Response $response The response that receives the expired cookies
     */
    public function clear(Request $request, Response $response): void;
}
