<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_string;

/**
 * Resolves the database-backed consent profile (and its locale copy) early in the request lifecycle.
 *
 * The result is stored on the main request ({@code nowo_cookie_consent_config}); templates read the
 * texts through {@code nowo_cookie_consent_trans()} so the shared translator is never mutated.
 */
final class CookieConsentConfigTranslationSubscriber implements EventSubscriberInterface
{
    /**
     * Creates a new config translation subscriber.
     *
     * @param CookieConsentConfigResolver $configResolver Resolves database-backed config
     */
    public function __construct(
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Returns the kernel events handled by this subscriber.
     *
     * @return array<string, array<int, mixed>> The subscribed event map
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 19],
        ];
    }

    /**
     * Resolves the consent configuration for the current request and stores it on the request.
     *
     * @param RequestEvent $event The kernel request event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (ColdStartRequestAttributes::shouldSkipDatabaseAccess($request)) {
            return;
        }

        try {
            $route    = $request->attributes->get('_route');
            $resolved = $this->configResolver->resolve(
                $request->getLocale(),
                is_string($route) && $route !== '' ? $route : null,
            );

            if (!$resolved instanceof ResolvedCookieConsentConfig) {
                return;
            }

            $request->attributes->set('nowo_cookie_consent_config', $resolved);
        } catch (DBALException|ORMException $exception) {
            $this->logger?->debug(
                'Skipping cookie consent config resolution during cold start.',
                ['exception' => $exception],
            );
        }
    }
}
