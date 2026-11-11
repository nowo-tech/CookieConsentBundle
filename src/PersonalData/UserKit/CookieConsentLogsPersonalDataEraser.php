<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\PersonalData\UserKit;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraserInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubjectResolverInterface;
use Nowo\UserKitBundle\PersonalData\ErasureRequest;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface;

/**
 * UserKit personal-data eraser `cookie_consent_logs` (1.29): deletes the user's consent log rows through
 * {@see CookieConsentPersonalDataEraserInterface}, once per subject the resolver returns.
 *
 * Registered when UserKit is installed, the bundle entities are mapped and `personal_data.enabled` is true. Priority
 * {@see self::PRIORITY}: before HttpLog's eraser (100), which deletes the request rows the IP windows come from, and
 * before the host identity eraser. Without an IP window provider (or a custom subject resolver) it erases nothing.
 */
final readonly class CookieConsentLogsPersonalDataEraser implements PersonalDataEraserInterface
{
    public const KEY = 'cookie_consent_logs';

    public const PRIORITY = 110;

    public function __construct(
        private CookieConsentPersonalDataEraserInterface $eraser,
        private CookieConsentPersonalDataSubjectResolverInterface $resolver,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(object $user): bool
    {
        return true;
    }

    public function erase(ErasureRequest $request): void
    {
        foreach (UserKitSubjects::resolve($this->resolver, $request->user, $request->identifier(), $request->context) as $subject) {
            $this->eraser->erase($subject);
        }
    }
}
