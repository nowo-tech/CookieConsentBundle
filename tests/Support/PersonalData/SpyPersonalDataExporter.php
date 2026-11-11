<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\PersonalData;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporterInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;

/**
 * Records the consent keys of the subjects it exports and returns one fixed decision (tests only).
 */
final class SpyPersonalDataExporter implements CookieConsentPersonalDataExporterInterface
{
    /** @var list<list<string>> */
    public array $keys = [];

    /** @var list<CookieConsentPersonalDataSubject> */
    public array $subjects = [];

    public function export(CookieConsentPersonalDataSubject $subject): array
    {
        $this->keys[]     = $subject->consentKeys();
        $this->subjects[] = $subject;

        return ['decisions' => [['cookie_name' => 'analytics', 'allowed' => true, 'consent_revision' => null, 'recorded_at' => '2026-01-01T00:00:00+00:00']]];
    }
}
