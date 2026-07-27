<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Default system clock when the host does not provide Psr\Clock\ClockInterface.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
