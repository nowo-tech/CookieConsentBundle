<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateInterval;

/**
 * Default {@see CookieConsentPersonalDataSubjectResolverInterface} (1.29): one subject made of the windows of every
 * {@see CookieConsentIpWindowProviderInterface}, each widened by the margin; none when no provider returns a window or
 * when erasure by IP window is off (default).
 */
final readonly class IpWindowSubjectResolver implements CookieConsentPersonalDataSubjectResolverInterface
{
    /**
     * @param iterable<CookieConsentIpWindowProviderInterface> $providers
     * @param string $margin ISO 8601 duration added before and after every window (`personal_data.ip_window_margin`)
     * @param bool $enabled whether erasure by IP window is on (`personal_data.ip_windows: erase`); off: no provider is
     *                      called and there is no subject
     */
    public function __construct(
        private iterable $providers = [],
        private string $margin = 'PT0S',
        private bool $enabled = false,
    ) {
    }

    public function subjectsFor(object $user, ?string $originalIdentifier): array
    {
        if (!$this->enabled) {
            return [];
        }

        $margin  = new DateInterval($this->margin);
        $windows = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->ipWindowsFor($user, $originalIdentifier) as $window) {
                $windows[] = $window->widen($margin);
            }
        }

        return $windows === [] ? [] : [CookieConsentPersonalDataSubject::of($windows)];
    }
}
