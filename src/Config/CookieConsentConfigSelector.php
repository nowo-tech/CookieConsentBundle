<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;

use const PHP_INT_MIN;

/**
 * Selects the highest-priority enabled consent configuration for a route.
 */
final class CookieConsentConfigSelector
{
    /**
     * Creates a new configuration selector.
     *
     * @param CookieConsentConfigRepository $configRepository Repository for consent profiles
     * @param CookieConsentRoutePatternMatcher $routePatternMatcher Matches route patterns
     */
    public function __construct(
        private readonly CookieConsentConfigRepository $configRepository,
        private readonly CookieConsentRoutePatternMatcher $routePatternMatcher,
    ) {
    }

    /**
     * Selects the consent configuration that best matches the current route.
     *
     * @param string|null $route The current route name, if any
     *
     * @return CookieConsentConfig|null The selected configuration or null
     */
    public function select(?string $route): ?CookieConsentConfig
    {
        if ($route === null || $route === '') {
            return $this->configRepository->findDefaultEnabled();
        }

        $bestMatch    = null;
        $bestPriority = PHP_INT_MIN;

        foreach ($this->configRepository->findAllEnabledNonDefault() as $config) {
            if (!$this->routePatternMatcher->matches($route, $config->getRoutePatterns())) {
                continue;
            }

            if ($config->getPriority() > $bestPriority) {
                $bestMatch    = $config;
                $bestPriority = $config->getPriority();
            }
        }

        return $bestMatch ?? $this->configRepository->findDefaultEnabled();
    }
}
