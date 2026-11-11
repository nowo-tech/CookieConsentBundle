<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

/**
 * SPI (1.29): tells the UserKit bridge on which client IPs, and when, a user was seen (e.g. from an HTTP access log).
 *
 * Autoconfigured with the `nowo_cookie_consent.personal_data.ip_window_provider` tag. The default subject resolver
 * widens every window by `personal_data.ip_window_margin` (default `P1D`) and erases / exports the consent rows of
 * those IPs inside the widened windows. Without any provider (and no custom `personal_data.subject_resolver`), the
 * bridge erases and exports nothing.
 *
 * Called once per erasure / export, possibly before the host erasers anonymise the user: use `$originalIdentifier`
 * (the identifier the user had when the erasure started) rather than the user's current state. Keep no state.
 */
interface CookieConsentIpWindowProviderInterface
{
    /**
     * Returns the IPs the user was seen on, each with its first and last time.
     *
     * @param object $user the user being erased / exported
     * @param string|null $originalIdentifier the user identifier before any eraser ran (null when unknown)
     *
     * @return iterable<CookieConsentIpWindow>
     */
    public function ipWindowsFor(object $user, ?string $originalIdentifier): iterable;
}
