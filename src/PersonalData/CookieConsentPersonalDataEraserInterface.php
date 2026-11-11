<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

/**
 * GDPR erasure port of the consent log (1.29): deletes the subject's consent log rows (art. 17).
 *
 * Only rows whose consent key equals one of the subject's keys, or (only when erasure by IP window is enabled,
 * `personal_data.ip_windows: erase`, off by default) whose stored IP equals a window's IP with a timestamp inside that
 * window, are deleted (byte-for-byte comparison in PHP after the SQL pre-filter, so a
 * case- or accent-insensitive collation never widens the match). Idempotent. Never flushes an entity manager.
 *
 * Transactions: when no transaction is open on the log's connection, each batch of at most 500 rows is deleted in its
 * own transaction and {@see CookieConsentPersonalDataErasedEvent} is dispatched after the last commit. Inside a
 * transaction the caller opened (UserKit runs every eraser inside one), the deletes join it and the event is NOT
 * dispatched: the returned result is the outcome.
 */
interface CookieConsentPersonalDataEraserInterface
{
    /**
     * Deletes the subject's consent log rows and returns how many were deleted.
     */
    public function erase(CookieConsentPersonalDataSubject $subject): CookieConsentErasureResult;
}
