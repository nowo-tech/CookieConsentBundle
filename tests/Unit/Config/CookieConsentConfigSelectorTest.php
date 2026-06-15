<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigSelector;
use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use PHPUnit\Framework\TestCase;

final class CookieConsentConfigSelectorTest extends TestCase
{
    public function testSelectsHighestPriorityMatchingProfile(): void
    {
        $default = (new CookieConsentConfig())
            ->setDefault(true)
            ->setEnabled(true)
            ->setName('Default');

        $admin = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setName('Admin')
            ->setRoutePatterns(['demo_admin_*'])
            ->setPriority(10)
            ->setConsentModalLayout('bar');

        $adminHigh = (new CookieConsentConfig())
            ->setEnabled(true)
            ->setName('Admin settings')
            ->setRoutePatterns(['demo_cookie_consent_config_*'])
            ->setPriority(20)
            ->setConsentModalLayout('cloud');

        $repository = $this->createMock(CookieConsentConfigRepository::class);
        $repository->method('findAllEnabledNonDefault')->willReturn([$admin, $adminHigh]);
        $repository->method('findDefaultEnabled')->willReturn($default);

        $selector = new CookieConsentConfigSelector($repository, new CookieConsentRoutePatternMatcher());

        $selected = $selector->select('demo_cookie_consent_config_index');

        self::assertNotNull($selected);
        self::assertSame('cloud', $selected->getConsentModalLayout());
    }

    public function testFallsBackToDefaultWhenNoPatternMatches(): void
    {
        $default = (new CookieConsentConfig())
            ->setDefault(true)
            ->setEnabled(true);

        $repository = $this->createMock(CookieConsentConfigRepository::class);
        $repository->method('findAllEnabledNonDefault')->willReturn([]);
        $repository->method('findDefaultEnabled')->willReturn($default);

        $selector = new CookieConsentConfigSelector($repository, new CookieConsentRoutePatternMatcher());

        self::assertSame($default, $selector->select('demo_home'));
    }

    public function testReturnsDefaultForNullOrEmptyRoute(): void
    {
        $default = (new CookieConsentConfig())->setDefault(true)->setEnabled(true);

        $repository = $this->createMock(CookieConsentConfigRepository::class);
        $repository->expects(self::exactly(2))->method('findDefaultEnabled')->willReturn($default);
        $repository->expects(self::never())->method('findAllEnabledNonDefault');

        $selector = new CookieConsentConfigSelector($repository, new CookieConsentRoutePatternMatcher());

        self::assertSame($default, $selector->select(null));
        self::assertSame($default, $selector->select(''));
    }
}
