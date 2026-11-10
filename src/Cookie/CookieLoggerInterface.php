<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Nowo\CookieConsentBundle\Event\ConsentSavedEvent;

/**
 * Stores the proof of consent when `use_logger` is on (1.26, DDD P2).
 *
 * {@see CookieLogger} is the default implementation (anonymised rows in the bundle's `CookieConsentLog` table). Alias
 * this interface to your own service to keep the log elsewhere (an append-only audit table, another entity manager).
 * Implementations must have stored the rows durably when {@see self::log()} returns, and throw when they could not:
 * {@see ConsentSavedEvent} is dispatched only after a successful call.
 */
interface CookieLoggerInterface
{
    /**
     * Logs the consent category choices of the current request.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     */
    public function log(array $categories, string $key): void;
}
