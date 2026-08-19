<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;

use function array_key_exists;

/**
 * Resolves database-backed cookie consent configuration for a locale and route.
 *
 * Results are memoized for the service lifetime (typically one request) so Twig,
 * sub-requests, and the config API do not repeat Doctrine lookups.
 */
final class CookieConsentConfigResolver
{
    /** @var array<string, ResolvedCookieConsentConfig|null> */
    private array $resolvedByLocaleAndRoute = [];

    /**
     * Creates a new configuration resolver.
     *
     * @param CookieConsentConfigSelector $configSelector Selects the active profile
     * @param CookieConsentConfigTranslationRepository $translationRepository Loads locale copy
     * @param bool $useDatabaseConfig Whether database-backed config is enabled
     */
    public function __construct(
        private readonly CookieConsentConfigSelector $configSelector,
        private readonly CookieConsentConfigTranslationRepository $translationRepository,
        private readonly bool $useDatabaseConfig,
    ) {
    }

    /**
     * Clears in-memory resolution caches after admin writes.
     *
     * @return void
     */
    public function clearRuntimeCache(): void
    {
        $this->resolvedByLocaleAndRoute = [];
    }

    /**
     * Resolves the best matching consent configuration for the given context.
     *
     * @param string $locale The requested locale code
     * @param string|null $route The current route name, if any
     *
     * @return ResolvedCookieConsentConfig|null The resolved configuration or null
     */
    public function resolve(string $locale, ?string $route = null): ?ResolvedCookieConsentConfig
    {
        if (!$this->useDatabaseConfig) {
            return null;
        }

        $cacheKey = $this->buildCacheKey($locale, $route);

        if (array_key_exists($cacheKey, $this->resolvedByLocaleAndRoute)) {
            return $this->resolvedByLocaleAndRoute[$cacheKey];
        }

        $config = $this->configSelector->select($route);

        if (!$config instanceof CookieConsentConfig) {
            return $this->resolvedByLocaleAndRoute[$cacheKey] = null;
        }

        $translation = $this->translationRepository->findOneForConfigAndLocale($config, $locale);

        return $this->resolvedByLocaleAndRoute[$cacheKey] = new ResolvedCookieConsentConfig($config, $translation);
    }

    private function buildCacheKey(string $locale, ?string $route): string
    {
        return $locale . "\0" . ($route ?? '');
    }
}
