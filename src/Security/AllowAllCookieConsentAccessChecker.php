<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Security;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final class AllowAllCookieConsentAccessChecker implements CookieConsentAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
