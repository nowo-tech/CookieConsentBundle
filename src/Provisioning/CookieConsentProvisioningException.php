<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Provisioning;

use RuntimeException;
use Throwable;

/**
 * A provisioning call (1.36) that cannot be completed for a reason the caller can act on. Nothing of the call was
 * written (its transaction or savepoint was rolled back).
 */
final class CookieConsentProvisioningException extends RuntimeException
{
    /**
     * `upsertCookieDefinitions()` needs the enabled default profile: call `ensureDefaultProfile()` first.
     */
    public static function noDefaultProfile(): self
    {
        return new self('There is no enabled default cookie consent profile: call CookieConsentProvisionerInterface::ensureDefaultProfile() first.');
    }

    /**
     * A unique key refused the write after every attempt: a stored natural key (cookie name, locale) that the database
     * collation treats as equal to the requested one although it is not byte for byte the same (for example "NID" and
     * "nid" under a case-insensitive MySQL collation), or another writer that keeps inserting the same key.
     */
    public static function duplicateKey(Throwable $previous): self
    {
        return new self('A unique key refused the provisioning write: a stored cookie name or locale differs from the requested one only in case, accents or trailing spaces (the database collation treats them as equal), or another writer inserts the same key. Rename it in the admin pages or in the specification.', 0, $previous);
    }
}
