<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfigTranslation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves CookieConsent v3-style UX options from YAML config or database entities.
 */
final class CmpUxOptionsResolver
{
    /**
     * @param list<array<string, mixed>> $yamlPreferenceSections
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $colorTheme,
        private readonly bool $darkModeEnabled,
        private readonly bool $disableTransitions,
        private readonly bool $twoStepModal,
        private readonly bool $openPreferencesModal,
        private readonly bool $manageIframePlaceholders,
        private readonly bool $granularCookieSelection,
        private readonly bool $preferencesBubbleEnabled,
        private readonly string $preferencesBubblePosition,
        private readonly array $yamlPreferenceSections,
        private readonly bool $useDatabaseConfig,
    ) {
    }

    public function getColorTheme(): string
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->getColorTheme() : $this->colorTheme;
    }

    public function isDarkModeEnabled(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isDarkModeEnabled() : $this->darkModeEnabled;
    }

    public function isDisableTransitions(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isDisableTransitions() : $this->disableTransitions;
    }

    public function isTwoStepModal(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isTwoStepModal() : $this->twoStepModal;
    }

    public function isOpenPreferencesModal(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isOpenPreferencesModal() : $this->openPreferencesModal;
    }

    public function isManageIframePlaceholders(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isManageIframePlaceholders() : $this->manageIframePlaceholders;
    }

    public function isGranularCookieSelection(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isGranularCookieSelection() : $this->granularCookieSelection;
    }

    public function isPreferencesBubbleEnabled(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isPreferencesBubbleEnabled() : $this->preferencesBubbleEnabled;
    }

    public function getPreferencesBubblePosition(): string
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig
            ? $config->getPreferencesBubblePosition()
            : $this->preferencesBubblePosition;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPreferenceSections(): array
    {
        if ($this->useDatabaseConfig) {
            $translation = $this->getDatabaseTranslation();
            $sections    = $translation?->getPreferenceSections();

            if ($sections !== null && $sections !== []) {
                return $sections;
            }
        }

        return $this->yamlPreferenceSections;
    }

    private function getDatabaseConfig(): ?CookieConsentConfig
    {
        return $this->getResolvedConfig()?->getConfig();
    }

    private function getDatabaseTranslation(): ?CookieConsentConfigTranslation
    {
        return $this->getResolvedConfig()?->getTranslation();
    }

    private function getResolvedConfig(): ?ResolvedCookieConsentConfig
    {
        if (!$this->useDatabaseConfig) {
            return null;
        }

        foreach ([$this->requestStack->getCurrentRequest(), $this->requestStack->getMainRequest()] as $request) {
            if (!$request instanceof Request) {
                continue;
            }

            $resolved = $request->attributes->get('nowo_cookie_consent_config');

            if ($resolved instanceof ResolvedCookieConsentConfig) {
                return $resolved;
            }
        }

        return null;
    }
}
