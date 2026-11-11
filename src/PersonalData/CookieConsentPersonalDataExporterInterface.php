<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

/**
 * GDPR export port of the consent log (1.29): the subject's consent decisions (art. 15 / 20), matched like
 * {@see CookieConsentPersonalDataEraserInterface}.
 *
 * The result is JSON-serialisable: `decisions` is the list of rows, oldest first, each with `cookie_name` (the consent
 * category), `allowed` (bool), `consent_revision` (int|null: the profile revision answered) and `recorded_at` (ATOM).
 * Neither the consent key nor the IP is exported (pseudonymous identifiers shared with the browser, not decisions).
 */
interface CookieConsentPersonalDataExporterInterface
{
    /**
     * Returns the subject's consent decisions.
     *
     * @return array{decisions: list<array{cookie_name: string, allowed: bool, consent_revision: int|null, recorded_at: string}>}
     */
    public function export(CookieConsentPersonalDataSubject $subject): array;
}
