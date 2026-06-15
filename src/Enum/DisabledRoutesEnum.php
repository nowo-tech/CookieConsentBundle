<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Enum;

/**
 * Default route names where the consent modal must not auto-open.
 */
final class DisabledRoutesEnum
{
    public const DISABLED_ROUTE_PRIVACY = 'privacy';

    public const DISABLED_ROUTE_IMPRINT = 'imprint';
}
