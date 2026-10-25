<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces CookieConsentAccessCheckerInterface on admin CRUD routes (REQ-UI-002).
 * When the web UI is disabled, admin routes answer 404 (they are still imported by the bundle routing).
 */
final readonly class CookieConsentAdminAccessSubscriber implements EventSubscriberInterface
{
    private const ROUTE_PREFIXES = [
        'nowo_cookie_consent_cookie_definitions_',
        'nowo_cookie_consent_config_settings_',
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
     * Listens to the controller event to guard the admin routes.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    /**
     * Answers 404 when the web UI is disabled and denies access when the checker refuses it.
     */
    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if ($route === null || !$this->isAdminRoute((string) $route)) {
            return;
        }

        if (!$this->webUiEnabled) {
            throw new NotFoundHttpException('Cookie consent admin UI is disabled.');
        }

        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Cookie consent admin requires an authorized user.');
        }
    }

    private function isAdminRoute(string $route): bool
    {
        foreach (self::ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
