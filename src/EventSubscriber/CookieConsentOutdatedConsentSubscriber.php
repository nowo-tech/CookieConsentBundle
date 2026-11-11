<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Nowo\CookieConsentBundle\Cookie\ConsentCookieWriterInterface;
use Nowo\CookieConsentBundle\Cookie\CookieCheckerInterface;
use Nowo\CookieConsentBundle\Cookie\CookieHandlerInterface;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Nowo\CookieConsentBundle\Http\ConsentCookieResponseWriter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Expires an outdated consent in the browser (1.25, owner decision JH-L1).
 *
 * When the visitor's `Cookie_Consent` time predates the active profile's last re-consent request, the response expires
 * the consent, granular and category cookies (not `Cookie_Consent_Key`), so scripts that read them see no consent
 * either. Runs late on `kernel.response` (priority {@see self::PRIORITY}, after Symfony's `CacheAttributeListener` at
 * -10 and before the session listener at -1000), so a response made `public` or given `s-maxage` by `#[Cache]` is
 * seen as such and never carries the `Set-Cookie` (audit JR-L1). A response that already stores a fresh consent (the
 * visitor answered in this request) is left alone.
 *
 * @final since 1.25, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieConsentOutdatedConsentSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = -900;

    /**
     * Creates the subscriber.
     *
     * @param CookieCheckerInterface $cookieChecker Detects outdated consents
     * @param CookieHandlerInterface $cookieHandler Writes the expired cookies (deprecated port, used only when it is a
     *                                              host service that does not implement the new one)
     * @param ConsentCookieWriterInterface|null $cookieWriter Framework-free consent cookie port (1.30)
     */
    public function __construct(
        private readonly CookieCheckerInterface $cookieChecker,
        private readonly CookieHandlerInterface $cookieHandler,
        private readonly ?ConsentCookieWriterInterface $cookieWriter = null,
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
            KernelEvents::RESPONSE => ['onResponse', self::PRIORITY],
        ];
    }

    /**
     * Expires the consent cookies of an outdated consent on private responses.
     *
     * @param ResponseEvent $event The kernel response event
     */
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request  = $event->getRequest();
        $response = $event->getResponse();

        if (ColdStartRequestAttributes::shouldSkipDatabaseAccess($request)
            || $this->isSharedCacheable($response)
            || $this->storesFreshConsent($response)
            || !$this->cookieChecker->isConsentOutdated()) {
            return;
        }

        // @igor-ignore - Writes expired cookies on this response; the cookie ports hold no state.
        (new ConsentCookieResponseWriter($this->cookieHandler, $this->cookieWriter))->clear($request, $response);
    }

    private function isSharedCacheable(Response $response): bool
    {
        return $response->headers->hasCacheControlDirective('public')
            || $response->headers->hasCacheControlDirective('s-maxage');
    }

    private function storesFreshConsent(Response $response): bool
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === CookieNameEnum::COOKIE_CONSENT_NAME && !$cookie->isCleared()) {
                return true;
            }
        }

        return false;
    }
}
