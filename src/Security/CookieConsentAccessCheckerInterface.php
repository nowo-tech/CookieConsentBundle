<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Security;

/**
 * Access control for cookie consent admin CRUD routes (REQ-UI-002).
 */
interface CookieConsentAccessCheckerInterface
{
    public function canAccess(): bool;
}
