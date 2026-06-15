<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Repository\CookieConsentConfigTranslationRepository;

/**
 * Resolves database-backed cookie consent configuration for a locale and route.
 */
final class CookieConsentConfigResolver
{
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

        $config = $this->configSelector->select($route);

        if (!$config instanceof \Nowo\CookieConsentBundle\Entity\CookieConsentConfig) {
            return null;
        }

        $translation = $this->translationRepository->findOneForConfigAndLocale($config, $locale);

        return new ResolvedCookieConsentConfig($config, $translation);
    }
}
