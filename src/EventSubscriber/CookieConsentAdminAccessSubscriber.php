<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Nowo\CookieConsentBundle\Security\CookieConsentAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces CookieConsentAccessCheckerInterface on admin CRUD routes (REQ-UI-002).
 */
final readonly class CookieConsentAdminAccessSubscriber implements EventSubscriberInterface
{
    private const ROUTE_PREFIXES = [
        'nowo_cookie_consent_cookie_definitions_',
        'nowo_cookie_consent_config_settings_',
    ];

    public function __construct(
        private CookieConsentAccessCheckerInterface $accessChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if ($route === null || !$this->isAdminRoute((string) $route)) {
            return;
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
