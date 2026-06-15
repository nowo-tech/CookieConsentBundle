<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Controller;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

use function is_string;

/**
 * Renders the cookie consent modal and applies resolved configuration.
 */
class CookieConsentController
{
    /**
     * Creates a new cookie consent controller.
     *
     * @param list<string> $cookieConsentDisabledRoutes
     */
    public function __construct(
        private readonly Environment $twigEnvironment,
        private readonly FormFactoryInterface $formFactory,
        private readonly CookieChecker $cookieChecker,
        private readonly RouterInterface $router,
        private readonly LocaleResolver $localeResolver,
        private readonly RequestStack $requestStack,
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly TranslatorInterface $translator,
        private readonly bool $fetchConfigViaApi = false,
        private readonly string $uiTheme = 'bootstrap',
        private readonly ?string $formAction = null,
        private readonly array $cookieConsentDisabledRoutes = [],
    ) {
    }

    /**
     * Renders the cookie consent modal page.
     *
     * @param Request $request The current HTTP request
     *
     * @return Response The rendered consent modal response
     */
    #[Route('/cookie_consent', name: 'nowo_cookie_consent.show')]
    public function show(Request $request): Response
    {
        $this->setLocale($request);
        $resolvedConfig = $this->applyDatabaseConfig($request);

        $response = new Response(
            $this->twigEnvironment->render($this->resolveConsentTemplate(), [
                'form'            => $this->createCookieConsentForm()->createView(),
                'disabled_routes' => $this->cookieConsentDisabledRoutes,
                'display_config'  => $resolvedConfig,
                'config_api_url'  => $this->resolveConfigApiUrl($request),
            ]),
        );

        $response->setPrivate();
        $response->setMaxAge(0);

        return $response;
    }

    /**
     * Renders the consent modal only when the user has not saved preferences yet.
     *
     * @param Request $request The current HTTP request
     *
     * @return Response The modal response or an empty response
     */
    #[Route('/cookie_consent_alt', name: 'nowo_cookie_consent.show_if_not_set')]
    public function showIfCookieConsentNotSet(Request $request): Response
    {
        if (!$this->cookieChecker->isCookieConsentSavedByUser()) {
            return $this->show($request);
        }

        return new Response();
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    protected function createCookieConsentForm(): FormInterface
    {
        if ($this->formAction === null) {
            return $this->formFactory->create(CookieConsentType::class);
        }

        return $this->formFactory->create(
            CookieConsentType::class,
            null,
            ['action' => $this->router->generate($this->formAction)],
        );
    }

    protected function setLocale(Request $request): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest instanceof Request && $mainRequest->getLocale() !== '') {
            $request->setLocale($mainRequest->getLocale());

            return;
        }

        $request->setLocale($this->localeResolver->resolve($request));
    }

    protected function applyDatabaseConfig(Request $request): ?ResolvedCookieConsentConfig
    {
        $resolved = $this->configResolver->resolve($request->getLocale(), $this->resolvePageRoute($request));

        if (!$resolved instanceof ResolvedCookieConsentConfig) {
            return null;
        }

        $messages = $resolved->getTranslationMessages();

        if ($messages !== [] && $this->translator instanceof \Symfony\Component\Translation\Translator) {
            $this->translator->addResource(
                'array',
                $messages,
                $request->getLocale(),
                'NowoCookieConsentBundle',
            );
        }

        $request->attributes->set('nowo_cookie_consent_config', $resolved);

        return $resolved;
    }

    private function resolveConfigApiUrl(Request $request): ?string
    {
        if (!$this->fetchConfigViaApi) {
            return null;
        }

        $locale = $request->getLocale();
        $params = ['_locale' => $locale];
        $route  = $this->resolvePageRoute($request);

        if ($route !== '') {
            $params['route'] = $route;
        }

        try {
            return $this->router->generate('nowo_cookie_consent.config_localized', $params);
        } catch (Throwable) {
            $fallback = ['locale' => $locale];

            if ($route !== '') {
                $fallback['route'] = $route;
            }

            return $this->router->generate('nowo_cookie_consent.config', $fallback);
        }
    }

    private function resolvePageRoute(Request $request): ?string
    {
        $mainRequest = $this->requestStack->getMainRequest() ?? $request;
        $route       = $mainRequest->attributes->get('_route');

        if (!is_string($route) || $route === '') {
            return null;
        }

        return $route;
    }

    private function resolveConsentTemplate(): string
    {
        return match ($this->uiTheme) {
            'tailwind' => '@NowoCookieConsentBundle/cookie_consent.tailwind.html.twig',
            default    => '@NowoCookieConsentBundle/cookie_consent.html.twig',
        };
    }
}
