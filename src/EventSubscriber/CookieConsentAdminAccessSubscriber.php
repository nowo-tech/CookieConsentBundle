<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Closure;
use Nowo\CookieConsentBundle\Controller\CookieConsentConfigSettingsAdminController;
use Nowo\CookieConsentBundle\Controller\CookieDefinitionAdminController;
use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use ReflectionFunction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function explode;
use function is_a;
use function is_array;
use function is_object;
use function is_string;
use function str_starts_with;

/**
 * Enforces CookieConsentAccessCheckerInterface on admin CRUD routes (REQ-UI-002).
 * When the web UI is disabled, admin routes answer 404 (they are still imported by the bundle routing).
 *
 * An admin request is recognised by its **controller class** (1.28, audit LR-C-L1), not only by its route name: a host
 * that imports the bundle routes with a `name_prefix` (or renames them) gets the same check.
 */
final readonly class CookieConsentAdminAccessSubscriber implements EventSubscriberInterface
{
    private const ROUTE_PREFIXES = [
        'nowo_cookie_consent_cookie_definitions_',
        'nowo_cookie_consent_config_settings_',
    ];

    /** The admin controllers (subclasses included). */
    private const ADMIN_CONTROLLERS = [
        CookieDefinitionAdminController::class,
        CookieConsentConfigSettingsAdminController::class,
    ];

    /**
     * Receives the access checker and whether the admin web UI is enabled.
     */
    public function __construct(
        private CookieConsentAccessCheckerInterface $accessChecker,
        private bool $webUiEnabled = true,
    ) {
    }

    /**
     * Listens to the controller event to guard the admin routes, and (1.28) to the request after routing to 404 them
     * before their controller is resolved when the web UI is disabled (a host without Doctrine has no admin
     * controllers at all).
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST    => ['onKernelRequest', 0],
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    /**
     * Answers 404 for an admin route when the web UI is disabled, before the controller is resolved (1.28).
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;
        if (!$this->webUiEnabled && ($this->isAdminRoute($attributes->get('_route')) || $this->isAdminController($attributes->get('_controller')))) {
            throw new NotFoundHttpException('Cookie consent admin UI is disabled.');
        }
    }

    /**
     * Answers 404 when the web UI is disabled and denies access when the checker refuses it.
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->isAdminController($event->getController()) && !$this->isAdminRoute($event->getRequest()->attributes->get('_route'))) {
            return;
        }

        if (!$this->webUiEnabled) {
            throw new NotFoundHttpException('Cookie consent admin UI is disabled.');
        }

        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Cookie consent admin requires an authorized user.');
        }
    }

    /**
     * The controller (a resolved callable — [object, method], an invokable object or a closure bound to the controller
     * — or the `_controller` attribute: "Class::method", [class, method]) belongs to an admin controller class.
     */
    private function isAdminController(mixed $controller): bool
    {
        if ($controller instanceof Closure) {
            $controller = (new ReflectionFunction($controller))->getClosureThis();
        }
        if (is_string($controller)) {
            $controller = explode('::', $controller, 2)[0];
        } elseif (is_array($controller)) {
            $controller = $controller[0] ?? null;
        }
        if (is_object($controller)) {
            $controller = $controller::class;
        }
        if (!is_string($controller)) {
            return false;
        }
        foreach (self::ADMIN_CONTROLLERS as $class) {
            if (is_a($controller, $class, true)) {
                return true;
            }
        }

        return false;
    }

    private function isAdminRoute(mixed $route): bool
    {
        if (!is_string($route)) {
            return false;
        }
        foreach (self::ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
