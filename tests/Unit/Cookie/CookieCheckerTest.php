<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CookieCheckerTest extends TestCase
{
    public function testDetectsSavedConsent(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, date('r'));

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertTrue($checker->isCookieConsentSavedByUser());
    }

    public function testReturnsFalseWhenNoRequest(): void
    {
        $checker = new CookieChecker(new RequestStack());

        self::assertFalse($checker->isCookieConsentSavedByUser());
        self::assertFalse($checker->isCategoryAllowedByUser('analytics'));
    }

    public function testDetectsAllowedCategory(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertTrue($checker->isCategoryAllowedByUser('analytics'));
    }

    public function testRequiredCookieIsAlwaysAllowed(): void
    {
        $checker = new CookieChecker(new RequestStack());

        self::assertTrue($checker->isCookieAllowedByUser('session', 'required'));
    }

    public function testGranularPreferencesOverrideCategory(): void
    {
        $request = Request::create('/');
        $request->cookies->set(
            CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME,
            '{"_ga": true, "_fbp": false}',
        );

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertTrue($checker->isCookieAllowedByUser('_ga', 'analytics'));
        self::assertFalse($checker->isCookieAllowedByUser('_fbp', 'marketing'));
    }

    public function testGranularPreferencesFallBackToCategoryWhenCookieMissing(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, '{"_other": false}');

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertTrue($checker->isCookieAllowedByUser('_ga', 'analytics'));
    }

    public function testInvalidGranularJsonReturnsNull(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, 'not-json');

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertNull($checker->getGranularPreferences());
        self::assertFalse($checker->isCookieAllowedByUser('_ga', 'analytics'));
    }

    public function testGranularPreferencesCacheResult(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, '{"_ga": true}');

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertSame(['_ga' => true], $checker->getGranularPreferences());
        self::assertSame(['_ga' => true], $checker->getGranularPreferences());
    }

    public function testGranularPreferencesIgnoreNonStringCookieNames(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, '{"_ga": true, "123": false}');

        $checker = new CookieChecker($this->createRequestStack($request));

        self::assertSame(['_ga' => true], $checker->getGranularPreferences());
    }

    private function createRequestStack(Request $request): RequestStack
    {
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
