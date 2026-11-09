<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Http\ColdStartRequestAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function bin2hex;
use function in_array;
use function is_array;
use function random_bytes;

/**
 * Handles cookie consent form submission on the HTTP response event.
 *
 * @final since 1.19, will be final in 2.0 (not an extension point; decorate or replace the service instead)
 */
class CookieConsentFormSubscriber implements EventSubscriberInterface
{
    /**
     * Creates a new cookie consent form subscriber.
     *
     * @param FormFactoryInterface $formFactory Creates consent forms
     * @param CookieLogger $cookieLogger Persists optional consent logs
     * @param CookieHandler $cookieHandler Writes consent cookies
     * @param bool $useLogger Whether consent logging is enabled
     * @param LoggerInterface|null $logger Optional debug logger
     * @param CookieChecker|null $cookieChecker Detects outdated consents (1.25); null keeps the 1.24 behaviour
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly CookieLogger $cookieLogger,
        private readonly CookieHandler $cookieHandler,
        private readonly bool $useLogger,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?CookieChecker $cookieChecker = null,
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
            KernelEvents::RESPONSE => ['onResponse'],
        ];
    }

    /**
     * Processes a submitted consent form and persists cookies when valid.
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

        if (ColdStartRequestAttributes::shouldSkipDatabaseAccess($request)) {
            return;
        }

        try {
            $form = $this->createCookieConsentForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var array<string, bool|string> $data */
                $data = $form->getData();
                $this->handleFormSubmit($data, $request, $response);

                return;
            }

            $this->expireOutdatedConsent($request, $response);
        } catch (DBALException|ORMException $exception) {
            $this->logger?->debug(
                'Skipping cookie consent form handling during cold start.',
                ['exception' => $exception],
            );
        }
    }

    /**
     * Expires an outdated consent (1.25, JH-L1) so the browser state matches what the server applies.
     *
     * Skipped on responses marked `public`: a shared cache must never store a `Set-Cookie` that would clear the
     * consent of other visitors. The next private response expires them instead.
     *
     * @param Request $request The current request
     * @param Response $response The response that receives the expired cookies
     */
    private function expireOutdatedConsent(Request $request, Response $response): void
    {
        if (!$this->cookieChecker instanceof CookieChecker || !$this->cookieChecker->isConsentOutdated()) {
            return;
        }

        if ($response->headers->hasCacheControlDirective('public')) {
            return;
        }

        // @igor-ignore - Writes expired cookies on this response; CookieHandler holds no state.
        $this->cookieHandler->clear($request, $response);
    }

    /**
     * @param array<string, bool|string> $categories
     */
    protected function handleFormSubmit(array $categories, Request $request, Response $response): void
    {
        $cookieConsentKey = $this->getCookieConsentKey($request);
        $granularCookies  = $this->extractGranularCookies($categories);

        $this->cookieHandler->save($categories, $cookieConsentKey, $response, $granularCookies);

        if ($this->useLogger) {
            $this->cookieLogger->log($categories, $cookieConsentKey);
        }
    }

    /**
     * @param array<string, array<string, bool|string>|bool|string> $data
     *
     * @return array<string, bool>
     */
    private function extractGranularCookies(array $data): array
    {
        $cookies = $data['cookies'] ?? null;

        if (!is_array($cookies)) {
            return [];
        }

        $granular = [];

        foreach ($cookies as $name => $allowed) {
            if ($name === '') {
                continue;
            }

            $granular[$name] = in_array($allowed, [true, 'true', '1', 1], true);
        }

        return $granular;
    }

    protected function getCookieConsentKey(Request $request): string
    {
        // The key identifies the visitor's consent log rows (proof of consent): unguessable, not time-based.
        return $request->cookies->get(CookieNameEnum::COOKIE_CONSENT_KEY_NAME) ?? bin2hex(random_bytes(16));
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    protected function createCookieConsentForm(): FormInterface
    {
        return $this->formFactory->create(CookieConsentType::class);
    }
}
