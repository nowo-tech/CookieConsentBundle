<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\EventSubscriber;

use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentAdminAccessSubscriber;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CookieConsentAdminAccessSubscriberTest extends TestCase
{
    public function testSubscribesToKernelController(): void
    {
        self::assertArrayHasKey(
            KernelEvents::CONTROLLER,
            CookieConsentAdminAccessSubscriber::getSubscribedEvents(),
        );
    }

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

    public function testReturnsNotFoundForAdminRoutesWhenWebUiDisabled(): void
    {
        $checker = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new CookieConsentAdminAccessSubscriber($checker, false);

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelController($this->createEvent('nowo_cookie_consent_cookie_definitions_delete'));
    }

    public function testIgnoresNonAdminRoutesWhenWebUiDisabled(): void
    {
        $checker = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new CookieConsentAdminAccessSubscriber($checker, false);
        $subscriber->onKernelController($this->createEvent('nowo_cookie_consent.config'));
        $this->addToAssertionCount(1);
    }

    public function testRecognisesAdminControllersWhateverTheRouteName(): void
    {
        // Audit LR-C-L1: a host route name prefix ("admin_…") must not bypass the check.
        $checker = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $checker->method('canAccess')->willReturn(false);
        $subscriber = new CookieConsentAdminAccessSubscriber($checker);
        $kernel     = $this->createMock(HttpKernelInterface::class);
        $request    = Request::create('/admin');
        $request->attributes->set('_route', 'admin_nowo_cookie_consent_cookie_definitions_index');
        $controller = (new ReflectionClass(CookieDefinitionAdminController::class))->newInstanceWithoutConstructor();

        foreach ([$controller->index(...)] as $callable) {
            try {
                $subscriber->onKernelController(new ControllerEvent($kernel, $callable, $request, HttpKernelInterface::MAIN_REQUEST));
                self::fail('access denied expected');
            } catch (AccessDeniedException) {
                $this->addToAssertionCount(1);
            }
        }

        // A route that is neither an admin name nor an admin controller is left alone, whatever the callable.
        $request->attributes->set('_route', 'app_home');
        $subscriber->onKernelController(new ControllerEvent($kernel, static fn (): string => 'ok', $request, HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onKernelController(new ControllerEvent($kernel, new class {
            public function __invoke(): string
            {
                return 'ok';
            }
        }, $request, HttpKernelInterface::MAIN_REQUEST));
    }

    public function testDisabledWebUiAnswersNotFoundByControllerAttribute(): void
    {
        $checker    = $this->createMock(CookieConsentAccessCheckerInterface::class);
        $subscriber = new CookieConsentAdminAccessSubscriber($checker, false);
        $kernel     = $this->createMock(HttpKernelInterface::class);

        foreach ([CookieConsentConfigSettingsAdminController::class . '::edit', [CookieDefinitionAdminController::class, 'index']] as $controller) {
            $request = Request::create('/admin');
            $request->attributes->set('_route', 'renamed');
            $request->attributes->set('_controller', $controller);
            try {
                $subscriber->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
                self::fail('404 expected');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }

        // Public routes and closures are left alone.
        $public = Request::create('/');
        $public->attributes->set('_controller', static fn (): string => 'ok');
        $subscriber->onKernelRequest(new RequestEvent($kernel, $public, HttpKernelInterface::MAIN_REQUEST));
        $public->attributes->set('_controller', 'App\\Controller\\HomeController::index');
        $subscriber->onKernelRequest(new RequestEvent($kernel, $public, HttpKernelInterface::MAIN_REQUEST));
        $this->addToAssertionCount(1);
    }

    private function createEvent(string $route): ControllerEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/admin');
        $request->attributes->set('_route', $route);

        return new ControllerEvent($kernel, static fn (): string => 'ok', $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
