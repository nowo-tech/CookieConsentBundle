<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Nowo\CookieConsentBundle\Config\CookieInventoryProvider;
use Nowo\CookieConsentBundle\Config\CmpUxOptionsResolver;
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
     * @param list<string> $cookieConsentDisabledRoutes
     */
    public function __construct(
        private readonly CookieChecker $cookieChecker,
        private readonly LocaleResolver $localeResolver,
        private readonly RequestStack $requestStack,
        private readonly CookieConsentRouteTargeting $routeTargeting,
        private readonly string $yamlRouteTargetingMode,
        private readonly array $yamlTargetRoutes,
        private readonly bool $useDatabaseConfig,
        private readonly bool $fetchConfigViaApi,
        private readonly array $cookieConsentDisabledRoutes,
        private readonly CmpUxOptionsResolver $cmpUxOptionsResolver,
        private readonly CookieInventoryProvider $inventoryProvider,
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
            new TwigFunction('nowo_cookie_consent_color_theme', $this->getColorTheme(...)),
            new TwigFunction('nowo_cookie_consent_dark_mode', $this->isDarkModeEnabled(...)),
            new TwigFunction('nowo_cookie_consent_disable_transitions', $this->isDisableTransitions(...)),
            new TwigFunction('nowo_cookie_consent_two_step_modal', $this->isTwoStepModal(...)),
            new TwigFunction('nowo_cookie_consent_open_preferences_modal', $this->isOpenPreferencesModal(...)),
            new TwigFunction('nowo_cookie_consent_manage_iframe_placeholders', $this->isManageIframePlaceholders(...)),
            new TwigFunction('nowo_cookie_consent_granular_cookie_selection', $this->isGranularCookieSelection(...)),
            new TwigFunction('nowo_cookie_consent_preference_sections', $this->getPreferenceSections(...)),
            new TwigFunction('nowo_cookie_consent_cookie_inventory', $this->getCookieInventory(...)),
            new TwigFunction('nowo_cookie_consent_should_embed_modal', $this->shouldEmbedModal(...)),
            new TwigFunction('nowo_cookie_consent_preferences_bubble_enabled', $this->isPreferencesBubbleEnabled(...)),
            new TwigFunction('nowo_cookie_consent_preferences_bubble_position', $this->getPreferencesBubblePosition(...)),
            new TwigFunction('nowo_cookie_consent_diagnostic_report', $this->getDiagnosticReport(...)),
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
     * Builds a structured diagnostic report for browser console debugging.
     *
     * @return array<string, mixed>
     */
    public function getDiagnosticReport(?string $currentRoute = null): array
    {
        $fallbackRoute = is_string($currentRoute) ? $currentRoute : '';
        $route         = $this->resolvePageRoute($fallbackRoute);
        $consentSaved  = $this->cookieChecker->isCookieConsentSavedByUser();
        $resolved      = $this->getResolvedConfig();
        $openBlockers  = $this->resolveOpenBlockers($route, $this->cookieConsentDisabledRoutes, $resolved);
        $openByDefault = $openBlockers === [] ? 'true' : 'false';

        return [
            'server' => [
                'consent_saved'       => $consentSaved,
                'will_embed_modal'    => !$consentSaved,
                'current_route'       => $route,
                'locale'              => $this->getResolvedLocale(),
                'open_by_default'     => $openByDefault,
                'open_blockers'       => $openBlockers,
                'disabled_routes'     => $this->cookieConsentDisabledRoutes,
                'use_database_config' => $this->useDatabaseConfig,
                'fetch_config_via_api'=> $this->fetchConfigViaApi,
                'resolved_config'     => $this->buildResolvedConfigSnapshot($resolved),
            ],
        ];
    }

    /**
     * @param list<string> $disabledRoutes
     *
     * @return list<string>
     */
    private function resolveOpenBlockers(
        string $currentRoute,
        array $disabledRoutes,
        ?ResolvedCookieConsentConfig $resolved,
    ): array {
        $blockers = [];

        if ($this->cookieChecker->isCookieConsentSavedByUser()) {
            $blockers[] = 'consent_already_saved';
        }

        if ($resolved instanceof ResolvedCookieConsentConfig && !$resolved->shouldAutoShow()) {
            $blockers[] = 'auto_show_disabled_in_config';
        }

        [$mode, $targetRoutes] = $this->resolveRouteTargeting($resolved);

        if (!$this->routeTargeting->shouldOpenOnRoute($currentRoute, $disabledRoutes, $mode, $targetRoutes)) {
            if ($currentRoute === '') {
                $blockers[] = 'empty_route_name';
            } elseif ($this->routeTargeting->shouldOpenOnRoute($currentRoute, $disabledRoutes, CookieConsentRouteTargeting::MODE_ALL, [])) {
                $blockers[] = match ($mode) {
                    CookieConsentRouteTargeting::MODE_ONLY   => 'route_not_in_only_list',
                    CookieConsentRouteTargeting::MODE_EXCEPT => 'route_in_except_list',
                    default                                  => 'route_targeting_blocked',
                };
            } else {
                $blockers[] = 'route_in_disabled_routes_list';
            }
        }

        return $blockers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildResolvedConfigSnapshot(?ResolvedCookieConsentConfig $resolved): ?array
    {
        if (!$resolved instanceof ResolvedCookieConsentConfig) {
            return null;
        }

        $config = $resolved->getConfig();

        return [
            'name'                 => $config->getName(),
            'auto_show'            => $config->isAutoShow(),
            'auto_show_route_mode' => $config->getAutoShowRouteMode(),
            'auto_show_routes'     => $config->getAutoShowRoutes(),
            'hide_from_bots'       => $config->isHideFromBots(),
            'two_step_modal'       => $config->isTwoStepModal(),
        ];
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

    public function getColorTheme(): string
    {
        return $this->cmpUxOptionsResolver->getColorTheme();
    }

    public function isDarkModeEnabled(): bool
    {
        return $this->cmpUxOptionsResolver->isDarkModeEnabled();
    }

    public function isDisableTransitions(): bool
    {
        return $this->cmpUxOptionsResolver->isDisableTransitions();
    }

    public function isTwoStepModal(): bool
    {
        return $this->cmpUxOptionsResolver->isTwoStepModal();
    }

    public function isOpenPreferencesModal(): bool
    {
        return $this->cmpUxOptionsResolver->isOpenPreferencesModal();
    }

    public function isManageIframePlaceholders(): bool
    {
        return $this->cmpUxOptionsResolver->isManageIframePlaceholders();
    }

    public function isGranularCookieSelection(): bool
    {
        return $this->cmpUxOptionsResolver->isGranularCookieSelection();
    }

    /**
     * @return list<array{title: string, description: string, categories: list<string>}>
     */
    public function getPreferenceSections(): array
    {
        return $this->cmpUxOptionsResolver->getPreferenceSections();
    }

    /**
     * @return list<array{name: string, provider: string, purpose: string, duration: string, category: string, type: string}>
     */
    public function getCookieInventory(): array
    {
        $resolved = $this->getResolvedConfig();
        $config   = $resolved?->getConfig();

        if (!$config instanceof CookieConsentConfig) {
            return [];
        }

        return $this->inventoryProvider->listForLocale($config, $this->getResolvedLocale());
    }

    public function shouldEmbedModal(): bool
    {
        return !$this->cookieChecker->isCookieConsentSavedByUser()
            || $this->cmpUxOptionsResolver->isPreferencesBubbleEnabled();
    }

    public function isPreferencesBubbleEnabled(): bool
    {
        return $this->cmpUxOptionsResolver->isPreferencesBubbleEnabled();
    }

    public function getPreferencesBubblePosition(): string
    {
        return $this->cmpUxOptionsResolver->getPreferencesBubblePosition();
    }
}
