<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigPayloadFactory;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes cookie consent configuration as a JSON API.
 */
final class CookieConsentConfigApiController
{
    /**
     * Creates a new config API controller.
     *
     * @param CookieConsentConfigPayloadFactory $payloadFactory Builds JSON payloads
     * @param LocaleResolver $localeResolver Resolves request locales
     */
    public function __construct(
        private readonly CookieConsentConfigPayloadFactory $payloadFactory,
        private readonly LocaleResolver $localeResolver,
    ) {
    }

    /**
     * Returns the cookie consent configuration for the resolved locale.
     *
     * @param Request $request The current HTTP request
     *
     * @return JsonResponse The configuration payload
     */
    #[Route('/cookie-consent/config', name: 'nowo_cookie_consent.config', methods: ['GET'])]
    public function getConfig(Request $request): JsonResponse
    {
        return $this->createResponse($this->resolveLocale($request), $this->resolveRoute($request));
    }

    #[Route(
        '/{_locale}/cookie-consent/config',
        name: 'nowo_cookie_consent.config_localized',
        requirements: ['_locale' => '[a-z]{2}(-[A-Z]{2})?'],
        methods: ['GET'],
    )]
    /**
     * Returns the cookie consent configuration for a specific locale.
     *
     * @param string $_locale The requested locale code
     * @param Request $request The current HTTP request
     *
     * @return JsonResponse The configuration payload
     */
    public function getLocalizedConfig(string $_locale, Request $request): JsonResponse
    {
        $request->setLocale($_locale);

        return $this->createResponse($_locale, $this->resolveRoute($request));
    }

    private function resolveRoute(Request $request): ?string
    {
        $route = $request->query->getString('route');

        return $route !== '' ? $route : null;
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->query->getString('locale');

        if ($locale !== '') {
            return $locale;
        }

        if ($request->getLocale() !== '') {
            return $request->getLocale();
        }

        return $this->localeResolver->resolve($request);
    }

    private function createResponse(string $locale, ?string $route = null): JsonResponse
    {
        $payload = $this->payloadFactory->build($locale, $route);

        $response = new JsonResponse($payload, $payload['code']);
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
