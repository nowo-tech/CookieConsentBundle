<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

/**
 * SPI (1.29): turns a user into the consent-log subjects the UserKit bridge erases / exports.
 *
 * The default ({@see IpWindowSubjectResolver}) builds one subject from the tagged
 * {@see CookieConsentIpWindowProviderInterface} services. Set `personal_data.subject_resolver` to your own service, for
 * example when your app records each user's `Cookie_Consent_Key` (from
 * {@see \Nowo\CookieConsentBundle\Event\ConsentSavedEvent}) and can return
 * {@see CookieConsentPersonalDataSubject::fromConsentKey()} subjects. Keep no state.
 */
interface CookieConsentPersonalDataSubjectResolverInterface
{
    /**
     * @param object $user the user being erased / exported
     * @param string|null $originalIdentifier the user identifier before any eraser ran (null when unknown)
     *
     * @return list<CookieConsentPersonalDataSubject> no subject: nothing to erase / export
     */
    public function subjectsFor(object $user, ?string $originalIdentifier): array;
}
