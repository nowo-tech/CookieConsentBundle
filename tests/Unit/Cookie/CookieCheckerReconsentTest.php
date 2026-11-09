<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Owner decision JH-L1 (1.25): a consent saved before the active profile's last re-consent request counts as no
 * consent. The comparison uses the `Cookie_Consent` timestamp; legacy cookies are valid until a re-consent request.
 */
final class CookieCheckerReconsentTest extends TestCase
{
    public function testWithoutReconsentRequestAnOldConsentStaysValid(): void
    {
        $checker = $this->checker('2020-01-01T00:00:00Z', null);

        self::assertFalse($checker->isConsentOutdated());
        self::assertTrue($checker->isCookieConsentSavedByUser());
        self::assertTrue($checker->isCategoryAllowedByUser('analytics'));
        self::assertSame(['_ga' => true], $checker->getGranularPreferences());
    }

    public function testConsentSavedBeforeTheReconsentRequestIsOutdated(): void
    {
        $checker = $this->checker('2026-11-09T09:59:59Z', '2026-11-09T10:00:00Z');

        self::assertTrue($checker->hasConsentCookie());
        self::assertTrue($checker->isConsentOutdated());
        self::assertFalse($checker->isCookieConsentSavedByUser(), 'banner opens again');
        self::assertFalse($checker->isCategoryAllowedByUser('analytics'), 'only necessary cookies apply');
        self::assertFalse($checker->isCookieAllowedByUser('_ga', 'analytics'));
        self::assertTrue($checker->isCookieAllowedByUser('PHPSESSID', 'required'));
        self::assertNull($checker->getGranularPreferences());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validConsents(): iterable
    {
        yield 'same second' => ['2026-11-09T10:00:00Z'];
        yield 'after' => ['2026-11-09T10:00:01Z'];
        yield 'other offset, after' => ['2026-11-09T11:30:00+01:00'];
    }

    #[DataProvider('validConsents')]
    public function testConsentSavedAtOrAfterTheReconsentRequestIsValid(string $consentedAt): void
    {
        $checker = $this->checker($consentedAt, '2026-11-09T10:00:00Z');

        self::assertFalse($checker->isConsentOutdated());
        self::assertTrue($checker->isCookieConsentSavedByUser());
        self::assertTrue($checker->isCategoryAllowedByUser('analytics'));
    }

    public function testLegacyRfc2822ValueIsReadAsADate(): void
    {
        $checker = $this->checker('Mon, 03 Nov 2025 12:00:00 +0100', '2026-11-09T10:00:00Z');

        self::assertEquals(new DateTimeImmutable('2025-11-03T11:00:00Z'), $checker->getConsentSavedAt());
        self::assertTrue($checker->isConsentOutdated());

        self::assertFalse($this->checker('Mon, 03 Nov 2025 12:00:00 +0100', null)->isConsentOutdated(), 'legacy cookie valid without re-consent request');
    }

    public function testUnreadableConsentValueIsOutdatedOnlyOnceReconsentWasRequested(): void
    {
        self::assertNull($this->checker('not a date', null)->getConsentSavedAt());
        self::assertFalse($this->checker('not a date', null)->isConsentOutdated());
        self::assertTrue($this->checker('not a date', '2026-11-09T10:00:00Z')->isConsentOutdated());
    }

    public function testWithoutConsentCookieNothingIsOutdated(): void
    {
        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', $this->resolved('2026-11-09T10:00:00Z'));
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');
        $stack = new RequestStack();
        $stack->push($request);
        $checker = new CookieChecker($stack);

        self::assertFalse($checker->hasConsentCookie());
        self::assertFalse($checker->isConsentOutdated());
        self::assertFalse($checker->isCookieConsentSavedByUser());
        self::assertNull($checker->getConsentSavedAt());
    }

    public function testWithoutResolvedProfileNothingIsOutdated(): void
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, '2020-01-01T00:00:00Z');
        $stack = new RequestStack();
        $stack->push($request);

        self::assertFalse((new CookieChecker($stack))->isConsentOutdated());
    }

    public function testProfileOfTheCurrentSubRequestIsUsedWhenTheMainRequestHasNone(): void
    {
        $main = Request::create('/');
        $main->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, '2020-01-01T00:00:00Z');
        $sub = Request::create('/_fragment');
        $sub->attributes->set('nowo_cookie_consent_config', $this->resolved('2026-11-09T10:00:00Z'));
        $stack = new RequestStack();
        $stack->push($main);
        $stack->push($sub);

        self::assertTrue((new CookieChecker($stack))->isConsentOutdated());
    }

    private function checker(string $consentedAt, ?string $reconsentRequiredAt): CookieChecker
    {
        $request = Request::create('/');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, $consentedAt);
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, '{"_ga":true}');
        $request->attributes->set('nowo_cookie_consent_config', $this->resolved($reconsentRequiredAt));
        $stack = new RequestStack();
        $stack->push($request);

        return new CookieChecker($stack);
    }

    private function resolved(?string $reconsentRequiredAt): ResolvedCookieConsentConfig
    {
        $config = new CookieConsentConfig();
        if ($reconsentRequiredAt !== null) {
            $config->requireReconsent(new DateTimeImmutable($reconsentRequiredAt));
        }

        return new ResolvedCookieConsentConfig($config, null);
    }
}
