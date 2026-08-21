<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Render;

use Nowo\CookieConsentBundle\Config\CookieConsentConfigResolver;
use Nowo\CookieConsentBundle\Config\CookieConsentRouteTargeting;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Form\CookieConsentType;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment;

use function is_string;

/**
 * Renders the consent modal HTML in-process (no kernel sub-request).
 *
 * Prefer {@see self::renderHtml()} from Twig via {@code nowo_cookie_consent_render()}
 * instead of {@code {{ render(path('nowo_cookie_consent.show')) }}} when embedding
 * in a host layout.
 */
final class CookieConsentModalRenderer
{
    /**
     * @param list<string> $cookieConsentDisabledRoutes
     * @param list<string> $skipRenderRoutes
     * @param list<string> $renderRoutes
     */
    public function __construct(
        private readonly Environment $twigEnvironment,
        private readonly FormFactoryInterface $formFactory,
        private readonly RouterInterface $router,
        private readonly LocaleResolver $localeResolver,
        private readonly RequestStack $requestStack,
        private readonly CookieConsentConfigResolver $configResolver,
        private readonly TranslatorInterface $translator,
        private readonly CookieConsentRouteTargeting $routeTargeting,
        private readonly bool $fetchConfigViaApi = false,
        private readonly string $uiTheme = 'bootstrap',
        private readonly ?string $formAction = null,
        private readonly array $cookieConsentDisabledRoutes = [],
        private readonly array $skipRenderRoutes = [],
        private readonly array $renderRoutes = [],
    ) {
    }

    /**
     * Returns the consent modal HTML, or an empty string when rendering should be skipped.
     */
    public function renderHtml(?Request $request = null): string
    {
        $request ??= $this->requestStack->getCurrentRequest() ?? $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            return '';
        }

        if ($this->shouldSkipRender()) {
            return '';
        }

        $this->setLocale($request);
        $resolvedConfig = $this->applyDatabaseConfig($request);

        return $this->twigEnvironment->render($this->resolveConsentTemplate(), [
            'form'            => $this->createCookieConsentForm()->createView(),
            'disabled_routes' => $this->cookieConsentDisabledRoutes,
            'display_config'  => $resolvedConfig,
            'config_api_url'  => $this->resolveConfigApiUrl($request),
        ]);
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createCookieConsentForm(): FormInterface
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

    private function setLocale(Request $request): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest instanceof Request && $mainRequest->getLocale() !== '') {
            $request->setLocale($mainRequest->getLocale());

            return;
        }

        $request->setLocale($this->localeResolver->resolve($request));
    }

    private function applyDatabaseConfig(Request $request): ?ResolvedCookieConsentConfig
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest instanceof Request) {
            $resolved = $mainRequest->attributes->get('nowo_cookie_consent_config');

            if ($resolved instanceof ResolvedCookieConsentConfig) {
                $request->attributes->set('nowo_cookie_consent_config', $resolved);

                return $resolved;
            }
        }

        $resolved = $this->configResolver->resolve($request->getLocale(), $this->resolvePageRoute($request));

        if (!$resolved instanceof ResolvedCookieConsentConfig) {
            return null;
        }

        $messages = $resolved->getTranslationMessages();

        if ($messages !== [] && $this->translator instanceof Translator) {
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

    private function shouldSkipRender(): bool
    {
        $mainRequest = $this->requestStack->getMainRequest();
        $route       = '';

        if ($mainRequest instanceof Request) {
            $route = $this->resolvePageRoute($mainRequest) ?? '';
        }

        return $this->routeTargeting->shouldSkipRender($route, $this->skipRenderRoutes, $this->renderRoutes);
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
