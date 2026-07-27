<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CookieConsentAdminAccessSubscriberTest extends TestCase
{
    public function testIgnoresNonAdminRoutes(): void
    {
        $checker = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new CookieConsentAdminAccessSubscriber($checker);
        $subscriber->onKernelController($this->createEvent('nowo_cookie_consent.show'));
    }

    public function testDeniesWhenCheckerFails(): void
    {
        $checker = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $checker->method('canAccess')->willReturn(false);

        $subscriber = new CookieConsentAdminAccessSubscriber($checker);

        $this->expectException(AccessDeniedException::class);
        $subscriber->onKernelController($this->createEvent('nowo_cookie_consent_cookie_definitions_index'));
    }

    public function testAllowsWhenCheckerPasses(): void
    {
        $checker = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->willReturn(true);

        $subscriber = new CookieConsentAdminAccessSubscriber($checker);
        $subscriber->onKernelController($this->createEvent('nowo_cookie_consent_config_settings_edit'));
    }

    private function createEvent(string $route): ControllerEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/admin');
        $request->attributes->set('_route', $route);

        return new ControllerEvent($kernel, static fn (): string => 'ok', $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
