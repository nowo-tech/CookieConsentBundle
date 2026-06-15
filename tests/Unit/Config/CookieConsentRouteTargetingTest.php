<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use Nowo\CookieConsentBundle\Config\CookieConsentRouteTargeting;
use PHPUnit\Framework\TestCase;

final class CookieConsentRouteTargetingTest extends TestCase
{
    private CookieConsentRouteTargeting $targeting;

    protected function setUp(): void
    {
        $this->targeting = new CookieConsentRouteTargeting(new CookieConsentRoutePatternMatcher());
    }

    public function testOpensOnAllRoutesByDefault(): void
    {
        self::assertTrue($this->targeting->shouldOpenOnRoute('home', [], CookieConsentRouteTargeting::MODE_ALL, []));
    }

    public function testAlwaysDisabledRoutesTakePrecedence(): void
    {
        self::assertFalse($this->targeting->shouldOpenOnRoute('privacy', ['privacy'], CookieConsentRouteTargeting::MODE_ALL, []));
    }

    public function testOnlyModeSupportsRoutePatterns(): void
    {
        self::assertTrue($this->targeting->shouldOpenOnRoute('demo_admin_index', [], CookieConsentRouteTargeting::MODE_ONLY, ['demo_admin_*']));
        self::assertFalse($this->targeting->shouldOpenOnRoute('demo_home', [], CookieConsentRouteTargeting::MODE_ONLY, ['demo_admin_*']));
    }

    public function testExceptModeSupportsRoutePatterns(): void
    {
        self::assertFalse($this->targeting->shouldOpenOnRoute('demo_admin_index', [], CookieConsentRouteTargeting::MODE_EXCEPT, ['demo_admin_*']));
        self::assertTrue($this->targeting->shouldOpenOnRoute('demo_home', [], CookieConsentRouteTargeting::MODE_EXCEPT, ['demo_admin_*']));
    }
}
