<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

/**
 * Determines whether the consent modal should open on a given route.
 */
final class CookieConsentRouteTargeting
{
    /**
     * Creates a new route targeting helper.
     *
     * @param CookieConsentRoutePatternMatcher $routePatternMatcher Matches route patterns
     */
    public function __construct(
        private readonly CookieConsentRoutePatternMatcher $routePatternMatcher,
    ) {
    }

    public const MODE_ALL = 'all';

    public const MODE_ONLY = 'only';

    public const MODE_EXCEPT = 'except';

    /**
     * Returns whether the consent modal should open on the current route.
     *
     * @param string $currentRoute The current route name
     * @param list<string> $alwaysDisabledRoutes Routes where the modal must never open
     * @param string $mode The route targeting mode
     * @param list<string> $targetRoutes Routes used by the targeting mode
     *
     * @return bool True when the modal should open automatically
     */
    public function shouldOpenOnRoute(
        string $currentRoute,
        array $alwaysDisabledRoutes,
        string $mode,
        array $targetRoutes,
    ): bool {
        if ($currentRoute === '' || $this->routePatternMatcher->matches($currentRoute, $alwaysDisabledRoutes)) {
            return false;
        }

        return match ($mode) {
            self::MODE_ONLY   => $this->routePatternMatcher->matches($currentRoute, $targetRoutes),
            self::MODE_EXCEPT => !$this->routePatternMatcher->matches($currentRoute, $targetRoutes),
            default           => true,
        };
    }

    /**
     * Returns whether the consent fragment must not be rendered for the current route.
     *
     * @param string $currentRoute The current (main) request route name
     * @param list<string> $skipRenderRoutes Route name patterns that skip the fragment entirely
     * @param list<string> $renderRoutes When non-empty, only these patterns may render (whitelist)
     *
     * @return bool True when hosts should omit the consent markup and the controller returns empty
     */
    public function shouldSkipRender(string $currentRoute, array $skipRenderRoutes, array $renderRoutes = []): bool
    {
        if ($currentRoute === '') {
            return false;
        }

        if ($renderRoutes !== [] && !$this->routePatternMatcher->matches($currentRoute, $renderRoutes)) {
            return true;
        }

        if ($skipRenderRoutes === []) {
            return false;
        }

        return $this->routePatternMatcher->matches($currentRoute, $skipRenderRoutes);
    }
}
