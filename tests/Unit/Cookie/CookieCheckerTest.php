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

    private function createRequestStack(Request $request): RequestStack
    {
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
