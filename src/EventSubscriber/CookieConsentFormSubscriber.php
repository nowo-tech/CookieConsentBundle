<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventSubscriber;

use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function is_array;
use function is_string;

/**
 * Handles cookie consent form submission on the HTTP response event.
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
     */
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly CookieLogger $cookieLogger,
        private readonly CookieHandler $cookieHandler,
        private readonly bool $useLogger,
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

        $form = $this->createCookieConsentForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, bool|string> $data */
            $data = $form->getData();
            $this->handleFormSubmit($data, $request, $response);
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
            if (!is_string($name)) {
                continue;
            }

            $granular[$name] = in_array($allowed, [true, 'true', '1', 1], true);
        }

        return $granular;
    }

    protected function getCookieConsentKey(Request $request): string
    {
        return $request->cookies->get(CookieNameEnum::COOKIE_CONSENT_KEY_NAME) ?? uniqid('', true);
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    protected function createCookieConsentForm(): FormInterface
    {
        return $this->formFactory->create(CookieConsentType::class);
    }
}
