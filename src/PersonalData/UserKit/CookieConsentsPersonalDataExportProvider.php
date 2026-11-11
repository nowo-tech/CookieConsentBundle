<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData\UserKit;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataExporterInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;
use Nowo\UserKitBundle\PersonalData\PersonalDataExportProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UserKit export section `cookie_consents` (1.29): the user's consent decisions from
 * {@see CookieConsentPersonalDataExporterInterface} (`{"decisions": [...]}`), all resolved subjects merged so a row is
 * listed once. Registered like {@see CookieConsentLogsPersonalDataEraser}. Consent keys only: IP windows are never
 * exported, so without a custom subject resolver that returns consent keys the section holds no decision.
 */
final readonly class CookieConsentsPersonalDataExportProvider implements PersonalDataExportProviderInterface
{
    public const SECTION_KEY = 'cookie_consents';

    /** Built-in UserKit providers use negative priorities, host sections come first. */
    public const PRIORITY = -50;

    public function __construct(
        private CookieConsentPersonalDataExporterInterface $exporter,
        private CookieConsentPersonalDataSubjectResolverInterface $resolver,
    ) {
    }

    public function getSectionKey(): string
    {
        return self::SECTION_KEY;
    }

    public function supports(object $user): bool
    {
        return true;
    }

    public function export(object $user): mixed
    {
        $identifier = $user instanceof UserInterface ? $user->getUserIdentifier() : null;
        $merged     = null;
        foreach (UserKitSubjects::resolve($this->resolver, $user, $identifier, [], false) as $subject) {
            $merged = $merged instanceof CookieConsentPersonalDataSubject ? $merged->merge($subject) : $subject;
        }

        return $merged instanceof CookieConsentPersonalDataSubject ? $this->exporter->export($merged) : ['decisions' => []];
    }
}
