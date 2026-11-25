<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Fixtures\RemovedPorts\UnrelatedMissing;

use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterfaceTwin;

/**
 * An application class implementing an unrelated missing interface whose name only starts like the removed one: it
 * keeps Symfony's usual lazy error and the build succeeds (W1-1 fixture).
 */
final class UnrelatedService implements CookieHandlerInterfaceTwin
{
}
