<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Exception\ORMException;
use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
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
use function is_string;
use function preg_match;
use function random_bytes;
use function strlen;

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
     * @param CookieConsentConfigResolver|null $configResolver Resolves the profile of the answered page (1.25)
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly CookieLogger $cookieLogger,
        private readonly CookieHandler $cookieHandler,
        private readonly bool $useLogger,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?CookieConsentConfigResolver $configResolver = null,
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
                $this->rememberAnsweredProfile($form, $request);
                $this->handleFormSubmit($data, $request, $response);
            }
        } catch (DBALException|ORMException $exception) {
            $this->logger?->debug(
                'Skipping cookie consent form handling during cold start.',
                ['exception' => $exception],
            );
        }
    }

    /**
     * Stores the profile of the page the visitor answered on (`page_route`), for the consent log (1.25, audit JR-L3).
     *
     * With a dedicated `form_action` the POST route resolves another profile than the page. The value is the
     * visitor's own and only selects which profile `revision` their log rows record; it never changes consent.
     *
     * @param FormInterface<mixed> $form The submitted consent form
     * @param Request $request The current request
     */
    private function rememberAnsweredProfile(FormInterface $form, Request $request): void
    {
        if (!$this->configResolver instanceof CookieConsentConfigResolver || !$form->has(CookieConsentType::PAGE_ROUTE_FIELD)) {
            return;
        }

        $route = $form->get(CookieConsentType::PAGE_ROUTE_FIELD)->getData();

        if (!is_string($route) || $route === '' || strlen($route) > 255 || preg_match('/^[A-Za-z0-9_.\-]+$/', $route) !== 1) {
            return;
        }

        $resolved = $this->configResolver->resolve($request->getLocale(), $route);

        if ($resolved instanceof ResolvedCookieConsentConfig) {
            $request->attributes->set(CookieLogger::ANSWERED_CONFIG_ATTRIBUTE, $resolved);
        }
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
