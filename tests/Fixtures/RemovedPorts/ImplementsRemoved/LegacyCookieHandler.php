<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Fixtures\RemovedPorts\ImplementsRemoved;

use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;

/**
 * An application class still implementing the port removed in 2.0: it cannot be loaded (W1-1 fixture; never autoloaded
 * outside the kernel test that discovers it, excluded from static analysis).
 */
final class LegacyCookieHandler implements CookieHandlerInterface
{
}
