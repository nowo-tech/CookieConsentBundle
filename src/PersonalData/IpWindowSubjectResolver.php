<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData;

use DateInterval;

/**
 * Default {@see CookieConsentPersonalDataSubjectResolverInterface} (1.29): one subject made of the windows of every
 * {@see CookieConsentIpWindowProviderInterface}, each widened by the margin; none when no provider returns a window.
 */
final readonly class IpWindowSubjectResolver implements CookieConsentPersonalDataSubjectResolverInterface
{
    /**
     * @param iterable<CookieConsentIpWindowProviderInterface> $providers
     * @param string $margin ISO 8601 duration added before and after every window (`personal_data.ip_window_margin`)
     */
    public function __construct(
        private iterable $providers = [],
        private string $margin = 'P1D',
    ) {
    }

    public function subjectsFor(object $user, ?string $originalIdentifier): array
    {
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
