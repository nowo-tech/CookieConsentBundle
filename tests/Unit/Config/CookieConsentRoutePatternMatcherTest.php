<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Config;

use Nowo\CookieConsentBundle\Config\CookieConsentRoutePatternMatcher;
use PHPUnit\Framework\TestCase;

final class CookieConsentRoutePatternMatcherTest extends TestCase
{
    private CookieConsentRoutePatternMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new CookieConsentRoutePatternMatcher();
    }

    public function testMatchesExactRouteName(): void
    {
        self::assertTrue($this->matcher->matches('demo_home', ['demo_home']));
        self::assertFalse($this->matcher->matches('demo_home', ['demo_admin_home']));
    }

    public function testMatchesWildcardPatterns(): void
    {
        self::assertTrue($this->matcher->matches('demo_cookie_consent_config_index', ['demo_cookie_consent_*']));
        self::assertFalse($this->matcher->matches('demo_home', ['demo_admin_*']));
        self::assertTrue($this->matcher->matches('demo_cookie_consent_config_settings', ['demo_cookie_consent_*']));
    }

    public function testRejectsEmptyRouteAndBlankPatterns(): void
    {
        self::assertFalse($this->matcher->matches('', ['home']));
        self::assertFalse($this->matcher->matches('home', ['', '   ']));
    }

    public function testMatchesQuestionMarkPattern(): void
    {
        self::assertTrue($this->matcher->matches('home', ['ho?e']));
        self::assertFalse($this->matcher->matches('house', ['ho?e']));
    }
}
