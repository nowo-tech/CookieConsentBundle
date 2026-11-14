<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Http;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\ConsentCookieWriterPass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes the consent cookies through the port the bundle chose (1.30, REQ-ARCH-001 rule 3).
 *
 * In the container, {@see ConsentCookieWriterPass} decides
 * once at build: a writer is passed, or null for a host `CookieHandlerInterface` service that must keep the deprecated
 * `save()` / `clear()` path (the deprecation is reported there, not per request). A listener built by hand with only
 * the bundle's own `CookieHandler` uses it as the writer. Built per call from the listener's own constructor
 * arguments; holds no request data.
 *
 * @internal
 */
final readonly class ConsentCookieResponseWriter
{
    public function __construct(
        private CookieHandlerInterface $cookieHandler,
        private ?ConsentCookieWriterInterface $cookieWriter = null,
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
        $writer = $this->writer();

        if ($writer instanceof ConsentCookieWriterInterface) {
            ConsentCookieJarApplier::apply($writer->consentCookies($categories, $key, $granularCookies), $response);

            return;
        }

        $this->cookieHandler->save($categories, $key, $response, $granularCookies);
    }

    /**
     * Expires the consent cookies the request carries on the response.
     */
    public function clear(Request $request, Response $response): void
    {
        $writer = $this->writer();

        if ($writer instanceof ConsentCookieWriterInterface) {
            ConsentCookieJarApplier::apply(
                $writer->expiredConsentCookies(ConsentCookieJarApplier::requestCookies($request)),
                $response,
            );

            return;
        }

        // @igor-ignore - Writes expired cookies on this response; the deprecated handler port holds no state.
        $this->cookieHandler->clear($request, $response);
    }

    private function writer(): ?ConsentCookieWriterInterface
    {
        if ($this->cookieWriter instanceof ConsentCookieWriterInterface) {
            return $this->cookieWriter;
        }

        // Only the bundle's own class: a subclass may override saveCookie() (audit MR-C-M2).
        return $this->cookieHandler::class === CookieHandler::class ? $this->cookieHandler : null;
    }
}
