<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Request attribute contract for skipping database-backed consent during cold start.
 */
final class ColdStartRequestAttributes
{
    /**
     * Shared with Site Backup Bundle: set to false when the application schema is not ready yet.
     */
    public const SITE_BACKUP_SCHEMA_EXISTS = '_nowo_site_backup_schema_exists';

    /**
     * Local override: set to false to skip consent database access on this request.
     */
    public const COOKIE_CONSENT_SCHEMA_READY = '_nowo_cookie_consent_schema_ready';

    /**
     * Returns whether database-backed consent work must be skipped for this request.
     *
     * @return bool
     */
    public static function shouldSkipDatabaseAccess(Request $request): bool
    {
        if ($request->attributes->get(self::COOKIE_CONSENT_SCHEMA_READY) === false) {
            return true;
        }

        return $request->attributes->get(self::SITE_BACKUP_SCHEMA_EXISTS) === false

        ;
    }
}
