<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Nowo\CookieConsentBundle\Config\CookieConsentRouteTargeting;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Locale\LocaleResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_string;

/**
 * Exposes cookie consent helpers as Twig functions.
 */
class CookieConsentTwigExtension extends AbstractExtension
{
    /**
     * Creates a new Twig extension for cookie consent helpers.
     *
     * @param list<string> $yamlTargetRoutes
     */
    public function __construct(
        private readonly CookieChecker $cookieChecker,
        private readonly LocaleResolver $localeResolver,
        private readonly RequestStack $requestStack,
        private readonly CookieConsentRouteTargeting $routeTargeting,
        private readonly string $yamlRouteTargetingMode,
        private readonly array $yamlTargetRoutes,
        private readonly bool $useDatabaseConfig,
    ) {
    }

    /**
     * Registers Twig functions provided by this extension.
     *
     * @return array<int, TwigFunction> The registered Twig functions
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_cookie_consent_is_saved', $this->isCookieConsentSavedByUser(...)),
            new TwigFunction('nowo_cookie_consent_is_category_allowed', $this->isCategoryAllowedByUser(...)),
            new TwigFunction('nowo_cookie_consent_is_open_by_default', $this->isCookieConsentOpenByDefault(...)),
            new TwigFunction('nowo_cookie_consent_enabled_locales', $this->getEnabledLocales(...)),
            new TwigFunction('nowo_cookie_consent_locale', $this->getResolvedLocale(...)),
        ];
    }

    /**
     * Returns the locales enabled for the cookie consent UI.
     *
     * @return list<string>
     */
    public function getEnabledLocales(): array
    {
        return $this->localeResolver->getEnabledLocales();
    }

    /**
     * Returns the locale resolved for the current request.
     *
     * @return string The resolved locale code
     */
    public function getResolvedLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof \Symfony\Component\HttpFoundation\Request) {
            return $this->localeResolver->getDefaultLocale();
        }

        return $this->localeResolver->resolve($request);
    }

    /**
     * Returns whether the consent modal should open by default on the current route.
     *
     * @param list<string> $disabledRoutes
     * @param string $currentRoute The current route name
     * @param list<string> $disabledRoutes Routes where the modal must not open
     *
     * @return string The string "true" or "false" for use in Twig attributes
     */
    public function isCookieConsentOpenByDefault(string $currentRoute, array $disabledRoutes): string
    {
        $currentRoute = $this->resolvePageRoute($currentRoute);

        if ($this->cookieChecker->isCookieConsentSavedByUser()) {
            return 'false';
        }

        $resolved = $this->getResolvedConfig();

        if ($resolved instanceof ResolvedCookieConsentConfig && !$resolved->shouldAutoShow()) {
            return 'false';
        }

        [$mode, $targetRoutes] = $this->resolveRouteTargeting($resolved);

        if (!$this->routeTargeting->shouldOpenOnRoute($currentRoute, $disabledRoutes, $mode, $targetRoutes)) {
            return 'false';
        }

        return 'true';
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function resolveRouteTargeting(?ResolvedCookieConsentConfig $resolved): array
    {
        if ($this->useDatabaseConfig && $resolved instanceof ResolvedCookieConsentConfig) {
            $config = $resolved->getConfig();

            return [
                $config->getAutoShowRouteMode(),
                $config->getAutoShowRoutes(),
            ];
        }

        return [
            $this->yamlRouteTargetingMode,
            CookieConsentConfig::parseRouteList(implode("\n", array_map(strval(...), $this->yamlTargetRoutes))),
        ];
    }

    private function getResolvedConfig(): ?ResolvedCookieConsentConfig
    {
        foreach ([$this->requestStack->getCurrentRequest(), $this->requestStack->getMainRequest()] as $request) {
            if (!$request instanceof \Symfony\Component\HttpFoundation\Request) {
                continue;
            }

            $resolved = $request->attributes->get('nowo_cookie_consent_config');

            if ($resolved instanceof ResolvedCookieConsentConfig) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolvePageRoute(string $fallbackRoute): string
    {
        $mainRequest    = $this->requestStack->getMainRequest();
        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($mainRequest instanceof \Symfony\Component\HttpFoundation\Request && $currentRequest instanceof \Symfony\Component\HttpFoundation\Request && $mainRequest !== $currentRequest) {
            $mainRoute = $mainRequest->attributes->get('_route');

            if (is_string($mainRoute) && $mainRoute !== '') {
                return $mainRoute;
            }
        }

        return $fallbackRoute;
    }

    /**
     * Returns whether the user has already saved cookie consent preferences.
     *
     * @return bool True when consent cookies are present
     */
    public function isCookieConsentSavedByUser(): bool
    {
        return $this->cookieChecker->isCookieConsentSavedByUser();
    }

    /**
     * Returns whether the given cookie category is allowed by the user.
     *
     * @param string $category The cookie category identifier
     *
     * @return bool True when the category cookie is set to allowed
     */
    public function isCategoryAllowedByUser(string $category): bool
    {
        return $this->cookieChecker->isCategoryAllowedByUser($category);
    }
}
