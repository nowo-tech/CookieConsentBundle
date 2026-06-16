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
     * Creates a new CMP UX options resolver.
     *
     * @param RequestStack $requestStack The HTTP request stack
     * @param string $colorTheme Default color theme from YAML
     * @param bool $darkModeEnabled Default dark mode flag from YAML
     * @param bool $disableTransitions Default transition flag from YAML
     * @param bool $disablePageInteraction Default page-interaction lock flag from YAML
     * @param bool $twoStepModal Default two-step modal flag from YAML
     * @param bool $openPreferencesModal Default open-preferences flag from YAML
     * @param bool $manageIframePlaceholders Default iframe management flag from YAML
     * @param bool $granularCookieSelection Default granular selection flag from YAML
     * @param bool $preferencesBubbleEnabled Default bubble flag from YAML
     * @param string $preferencesBubblePosition Default bubble position from YAML
     * @param string|null $preferencesBubbleBorderColor Default bubble border color from YAML
     * @param string|null $preferencesBubbleIcon Default bubble icon markup from YAML
     * @param list<array<string, mixed>> $yamlPreferenceSections Preference sections from YAML
     * @param bool $useDatabaseConfig Whether database-backed config is enabled
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $colorTheme,
        private readonly bool $darkModeEnabled,
        private readonly bool $disableTransitions,
        private readonly bool $disablePageInteraction,
        private readonly bool $twoStepModal,
        private readonly bool $openPreferencesModal,
        private readonly bool $manageIframePlaceholders,
        private readonly bool $granularCookieSelection,
        private readonly bool $preferencesBubbleEnabled,
        private readonly string $preferencesBubblePosition,
        private readonly ?string $preferencesBubbleBorderColor,
        private readonly ?string $preferencesBubbleIcon,
        private readonly array $yamlPreferenceSections,
        private readonly bool $useDatabaseConfig,
    ) {
    }

    /**
     * Returns the active modal color theme identifier.
     *
     * @return string The configured color theme
     */
    public function getColorTheme(): string
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->getColorTheme() : $this->colorTheme;
    }

    /**
     * Returns whether dark mode styling is enabled.
     *
     * @return bool True when dark mode classes are applied
     */
    public function isDarkModeEnabled(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isDarkModeEnabled() : $this->darkModeEnabled;
    }

    /**
     * Returns whether CSS transitions are disabled on the modal.
     *
     * @return bool True when transitions are suppressed
     */
    public function isDisableTransitions(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isDisableTransitions() : $this->disableTransitions;
    }

    /**
     * Returns whether page interaction is blocked until consent is given.
     *
     * @return bool True when a full-page overlay and scroll lock are applied
     */
    public function isDisablePageInteraction(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isDisablePageInteraction() : $this->disablePageInteraction;
    }

    /**
     * Returns whether the two-step modal flow is enabled.
     *
     * @return bool True when banner and preferences steps are used
     */
    public function isTwoStepModal(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isTwoStepModal() : $this->twoStepModal;
    }

    /**
     * Returns whether the preferences step opens by default.
     *
     * @return bool True when preferences are shown first
     */
    public function isOpenPreferencesModal(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isOpenPreferencesModal() : $this->openPreferencesModal;
    }

    /**
     * Returns whether iframe placeholders are managed after consent.
     *
     * @return bool True when iframe activation is enabled
     */
    public function isManageIframePlaceholders(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isManageIframePlaceholders() : $this->manageIframePlaceholders;
    }

    /**
     * Returns whether granular per-cookie selection is enabled.
     *
     * @return bool True when per-cookie toggles are shown
     */
    public function isGranularCookieSelection(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isGranularCookieSelection() : $this->granularCookieSelection;
    }

    /**
     * Returns whether the floating preferences bubble is enabled.
     *
     * @return bool True when the bubble button is rendered
     */
    public function isPreferencesBubbleEnabled(): bool
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig ? $config->isPreferencesBubbleEnabled() : $this->preferencesBubbleEnabled;
    }

    /**
     * Returns the screen corner used for the preferences bubble.
     *
     * @return string The configured bubble position
     */
    public function getPreferencesBubblePosition(): string
    {
        $config = $this->getDatabaseConfig();

        return $config instanceof CookieConsentConfig
            ? $config->getPreferencesBubblePosition()
            : $this->preferencesBubblePosition;
    }

    /**
     * Returns the hex color used for the preferences bubble border and icon.
     *
     * @return string|null A hex color or null to use the bundle default
     */
    public function getPreferencesBubbleBorderColor(): ?string
    {
        $config = $this->getDatabaseConfig();

        if ($config instanceof CookieConsentConfig) {
            $color = $config->getPreferencesBubbleBorderColor();

            if ($color !== null && $color !== '') {
                return $color;
            }
        }

        return $this->preferencesBubbleBorderColor;
    }

    /**
     * Returns custom HTML/SVG markup for the preferences bubble icon.
     *
     * @return string|null Sanitized icon markup or null to use the bundle default
     */
    public function getPreferencesBubbleIcon(): ?string
    {
        $config = $this->getDatabaseConfig();

        if ($config instanceof CookieConsentConfig) {
            $icon = $config->getPreferencesBubbleIcon();

            if ($icon !== null && $icon !== '') {
                return $icon;
            }
        }

        return $this->preferencesBubbleIcon;
    }

    /**
     * Returns grouped preference sections for the modal.
     *
     * @return list<array<string, mixed>> Preference section definitions
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
