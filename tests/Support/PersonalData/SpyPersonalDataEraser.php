<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support\PersonalData;

use Nowo\CookieConsentBundle\PersonalData\CookieConsentErasureResult;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataEraserInterface;
use Nowo\CookieConsentBundle\PersonalData\CookieConsentPersonalDataSubject;

/**
 * Records the subjects it is asked to erase (tests only).
 */
final class SpyPersonalDataEraser implements CookieConsentPersonalDataEraserInterface
{
    /** @var list<CookieConsentPersonalDataSubject> */
    public array $subjects = [];

    public function erase(CookieConsentPersonalDataSubject $subject): CookieConsentErasureResult
    {
        $this->subjects[] = $subject;

        return new CookieConsentErasureResult(1);
    }
}
