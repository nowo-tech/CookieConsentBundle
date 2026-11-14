<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

/**
 * The consent cookies to write on one response, without any HTTP type (1.30, REQ-ARCH-001 rule 3).
 *
 * Immutable and built per call by {@see ConsentCookieWriterInterface}; the bundle applies it to the response in its
 * `kernel.response` listeners. It never lives in a service, so a kept-alive worker carries nothing from one request to
 * the next.
 */
final readonly class ConsentCookieJar
{
    /**
     * @param list<ConsentCookie> $cookies The cookies, in the order they are written
     */
    public function __construct(
        private array $cookies = [],
    ) {
    }

    /**
     * Returns a jar with one more cookie.
     */
    public function with(ConsentCookie $cookie): self
    {
        $cookies   = $this->cookies;
        $cookies[] = $cookie;

        return new self($cookies);
    }

    /**
     * @return list<ConsentCookie>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function isEmpty(): bool
    {
        return $this->cookies === [];
    }
}
