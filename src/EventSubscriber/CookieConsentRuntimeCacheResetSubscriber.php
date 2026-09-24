<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Clears the per-request consent config caches at the start of every main request.
 *
 * Keeps the memoized profiles, texts and cookie tables scoped to one request even when the
 * kernel is not reset between requests (FrankenPHP worker mode without {@code services_resetter}),
 * so admin changes made in another worker are picked up on the next request. Sub-requests
 * (fragments, ESI) keep the caches of their main request.
 */
final class CookieConsentRuntimeCacheResetSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly CookieInventoryProvider $inventoryProvider,
        private readonly CookieConsentConfigRepository $configRepository,
    ) {
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->configResolver->reset();
        $this->inventoryProvider->reset();
        $this->configRepository->reset();
    }
}
