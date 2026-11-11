<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\PersonalData;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;

/**
 * Returns one consent-key subject per identifier (the identifier itself, or `$constant`), plus `$extra`, and records
 * the calls (tests only).
 */
final class SpySubjectResolver implements CookieConsentPersonalDataSubjectResolverInterface
{
    /** @var list<array{object, ?string}> */
    public array $calls = [];

    public function __construct(
        private readonly ?string $constant = null,
        private readonly ?string $extra = null,
    ) {
    }

    public function subjectsFor(object $user, ?string $originalIdentifier): array
    {
        $this->calls[] = [$user, $originalIdentifier];
        $subjects      = [CookieConsentPersonalDataSubject::fromConsentKey($this->constant ?? (string) $originalIdentifier)];
        if ($this->constant !== null) {
            $subjects[] = CookieConsentPersonalDataSubject::fromConsentKey($this->constant);
        }
        if ($this->extra !== null) {
            $subjects[] = CookieConsentPersonalDataSubject::fromConsentKey($this->extra);
        }

        return $subjects;
    }
}
