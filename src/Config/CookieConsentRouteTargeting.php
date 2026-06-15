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
}
