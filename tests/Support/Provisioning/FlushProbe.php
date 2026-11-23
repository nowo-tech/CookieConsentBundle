<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\Provisioning;

use Closure;
use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * Doctrine `onFlush` listener of the provisioning tests: counts the flushes and runs `$onFlush` with the 1-based
 * flush number (to fail or to write a conflicting row on chosen attempts).
 */
final class FlushProbe
{
    public int $flushes = 0;

    /**
     * @param Closure(OnFlushEventArgs, int): void $onFlush
     */
    public function __construct(private readonly Closure $onFlush)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        ++$this->flushes;
        ($this->onFlush)($args, $this->flushes);
    }
}
