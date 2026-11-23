<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use InvalidArgumentException;

/**
 * A provisioning value object (1.36) was built with a value the bundle cannot store: an unknown setting or key, a
 * value of the wrong type, a text longer than its column, an invalid choice or a malformed natural key. Thrown when
 * the value object is built, before anything is written.
 */
final class InvalidProvisioningSpecException extends InvalidArgumentException
{
}
