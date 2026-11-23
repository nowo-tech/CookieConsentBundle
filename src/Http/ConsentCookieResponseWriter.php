<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Http;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes the cookies the consent cookie port returns on the response (1.30, REQ-ARCH-001 rule 3; since 2.0 the port is
 * always {@see ConsentCookieWriterInterface}). Built per call from the listener's own constructor arguments; holds no
 * request data.
 *
 * @internal
 */
final readonly class ConsentCookieResponseWriter
{
    /**
     * Creates the ConsentCookieResponseWriter with its collaborator.
     */
    public function __construct(
        private ConsentCookieWriterInterface $cookieWriter,
    ) {
    }

    /**
     * Writes the cookies of a saved consent on the response.
     *
     * @param array<string, bool|string> $categories
     * @param array<string, bool> $granularCookies
     */
    public function save(array $categories, string $key, Response $response, array $granularCookies = []): void
    {
        ConsentCookieJarApplier::apply($this->cookieWriter->consentCookies($categories, $key, $granularCookies), $response);
    }

    /**
     * Expires the consent cookies the request carries on the response.
     */
    public function clear(Request $request, Response $response): void
    {
        ConsentCookieJarApplier::apply(
            $this->cookieWriter->expiredConsentCookies(ConsentCookieJarApplier::requestCookies($request)),
            $response,
        );
    }
}
