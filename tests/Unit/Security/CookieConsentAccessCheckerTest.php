<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Security;

use Nowo\CookieConsentBundle\Security\AllowAllCookieConsentAccessChecker;
use Nowo\CookieConsentBundle\Security\ConfigurableCookieConsentAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class CookieConsentAccessCheckerTest extends TestCase
{
    public function testConfigurableGrantsWhenRoleMatches(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $checker = new ConfigurableCookieConsentAccessChecker($auth, ['ROLE_ADMIN']);
        self::assertTrue($checker->canAccess());
    }

    public function testConfigurableDeniesWhenNoRoleMatches(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $checker = new ConfigurableCookieConsentAccessChecker($auth, ['ROLE_ADMIN']);
        self::assertFalse($checker->canAccess());
    }

    public function testConfigurableAllowsWhenRolesEmpty(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects(self::never())->method('isGranted');

        $checker = new ConfigurableCookieConsentAccessChecker($auth, []);
        self::assertTrue($checker->canAccess());
    }

    public function testAllowAllAlwaysTrue(): void
    {
        self::assertTrue((new AllowAllCookieConsentAccessChecker())->canAccess());
    }
}
