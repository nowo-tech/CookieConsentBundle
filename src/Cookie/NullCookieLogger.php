<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

/**
 * Logs nothing (1.28). The default {@see CookieLoggerInterface} of a host without Doctrine that keeps `use_logger:
 * false`; with `use_logger: true` such a host aliases the interface to its own store.
 */
final readonly class NullCookieLogger implements CookieLoggerInterface
{
    /**
     * Does nothing.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     */
    public function log(array $categories, string $key): void
    {
    }
}
