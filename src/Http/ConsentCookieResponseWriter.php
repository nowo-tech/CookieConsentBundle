<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Http;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Picks the consent cookie port the bundle's `kernel.response` listeners call (1.30, REQ-ARCH-001 rule 3).
 *
 * Order: a host service aliased to {@see ConsentCookieWriterInterface}; else the {@see CookieHandlerInterface}
 * service when it also implements the new port (the default {@see CookieHandler}); else the deprecated
 * `CookieHandlerInterface` methods, with a deprecation. A subclass of `CookieHandler` keeps the deprecated path, so an
 * overridden `saveCookie()` still runs. Built per call from the listener's own constructor arguments; holds no
 * request data.
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

        $this->triggerDeprecation();
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

        $this->triggerDeprecation();
        // @igor-ignore - Writes expired cookies on this response; the deprecated handler port holds no state.
        $this->cookieHandler->clear($request, $response);
    }

    private function writer(): ?ConsentCookieWriterInterface
    {
        if ($this->cookieWriter instanceof ConsentCookieWriterInterface && $this->cookieWriter::class !== CookieHandler::class) {
            return $this->cookieWriter;
        }

        if ($this->cookieHandler instanceof CookieHandler && $this->cookieHandler::class !== CookieHandler::class) {
            return null;
        }

        if ($this->cookieHandler instanceof ConsentCookieWriterInterface) {
            return $this->cookieHandler;
        }

        return null;
    }

    private function triggerDeprecation(): void
    {
        @trigger_error(sprintf(
            'Since nowo-tech/cookie-consent-bundle 1.30: the consent cookies are written through the deprecated "%s" (service "%s"); implement "%s" instead (removed in 2.0).',
            CookieHandlerInterface::class,
            $this->cookieHandler::class,
            ConsentCookieWriterInterface::class,
        ), E_USER_DEPRECATED);
    }
}
