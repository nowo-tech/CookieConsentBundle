<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\PersonalData;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;

/**
 * Returns the same subjects for every user (tests only).
 */
final readonly class FixedSubjectResolver implements CookieConsentPersonalDataSubjectResolverInterface
{
    /**
     * @param list<CookieConsentPersonalDataSubject> $subjects
     */
    public function __construct(
        private array $subjects,
    ) {
    }

    public function subjectsFor(object $user, ?string $originalIdentifier): array
    {
        return $this->subjects;
    }
}
