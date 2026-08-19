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
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

/**
 * Loads database-backed consent translations early in the request lifecycle.
 */
final class CookieConsentConfigTranslationSubscriber implements EventSubscriberInterface
{
    /**
     * Creates a new config translation subscriber.
     *
     * @param CookieConsentConfigResolver $configResolver Resolves database-backed config
     * @param TranslatorInterface $translator Registers runtime translation messages
     */
    public function __construct(
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly TranslatorInterface $translator,
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
     * Resolves and registers consent translations for the current request.
     *
     * @param RequestEvent $event The kernel request event
     *
     * @return void
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
            $locale   = $request->getLocale();
            $route    = $request->attributes->get('_route');
            $resolved = $this->configResolver->resolve(
                $locale,
                is_string($route) && $route !== '' ? $route : null,
            );

            if (!$resolved instanceof ResolvedCookieConsentConfig) {
                return;
            }

            $messages = $resolved->getTranslationMessages();

            if ($messages !== [] && $this->translator instanceof Translator) {
                $this->translator->addResource(
                    'array',
                    $messages,
                    $locale,
                    'NowoCookieConsentBundle',
                );
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
