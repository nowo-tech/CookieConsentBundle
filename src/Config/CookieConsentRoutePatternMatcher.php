<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

/**
 * Matches Symfony route names against configurable wildcard patterns.
 */
final class CookieConsentRoutePatternMatcher
{
    /**
     * Returns whether the route matches any of the given patterns.
     *
     * @param string $route The route name to test
     * @param list<string> $patterns The route patterns to match against
     *
     * @return bool True when at least one pattern matches
     */
    public function matches(string $route, array $patterns): bool
    {
        if ($route === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($route, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $route, string $pattern): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        if (!str_contains($pattern, '*') && !str_contains($pattern, '?')) {
            return $route === $pattern;
        }

        return fnmatch($pattern, $route);
    }
}
