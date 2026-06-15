<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Twig;

use Nowo\CookieConsentBundle\Config\CmpUxOptionsResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes CookieConsent v3-style UX options as Twig functions.
 */
final class CmpUxTwigExtension extends AbstractExtension
{
    /**
     * Creates a new CMP UX Twig extension.
     *
     * @param CmpUxOptionsResolver $uxOptionsResolver Resolves UX options from config or database
     */
    public function __construct(
        private readonly CmpUxOptionsResolver $uxOptionsResolver,
    ) {
    }

    /**
     * Registers Twig functions for CMP UX options.
     *
     * @return array<int, TwigFunction> Registered Twig functions
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_cookie_consent_two_step_modal', $this->isTwoStepModal(...)),
            new TwigFunction('nowo_cookie_consent_open_preferences_modal', $this->isOpenPreferencesModal(...)),
            new TwigFunction('nowo_cookie_consent_color_theme', $this->getColorTheme(...)),
            new TwigFunction('nowo_cookie_consent_dark_mode', $this->isDarkModeEnabled(...)),
            new TwigFunction('nowo_cookie_consent_disable_transitions', $this->isDisableTransitions(...)),
            new TwigFunction('nowo_cookie_consent_manage_iframe_placeholders', $this->isManageIframePlaceholders(...)),
            new TwigFunction('nowo_cookie_consent_granular_cookie_selection', $this->isGranularCookieSelection(...)),
            new TwigFunction('nowo_cookie_consent_preferences_bubble_enabled', $this->isPreferencesBubbleEnabled(...)),
            new TwigFunction('nowo_cookie_consent_preferences_bubble_position', $this->getPreferencesBubblePosition(...)),
            new TwigFunction('nowo_cookie_consent_preference_sections', $this->getPreferenceSections(...)),
        ];
    }

    /**
     * Returns whether the two-step modal flow is enabled.
     *
     * @return bool True when banner and preferences steps are used
     */
    public function isTwoStepModal(): bool
    {
        return $this->uxOptionsResolver->isTwoStepModal();
    }

    /**
     * Returns whether the preferences step opens by default.
     *
     * @return bool True when preferences are shown first
     */
    public function isOpenPreferencesModal(): bool
    {
        return $this->uxOptionsResolver->isOpenPreferencesModal();
    }

    /**
     * Returns the active modal color theme identifier.
     *
     * @return string The configured color theme
     */
    public function getColorTheme(): string
    {
        return $this->uxOptionsResolver->getColorTheme();
    }

    /**
     * Returns whether dark mode styling is enabled.
     *
     * @return bool True when dark mode classes are applied
     */
    public function isDarkModeEnabled(): bool
    {
        return $this->uxOptionsResolver->isDarkModeEnabled();
    }

    /**
     * Returns whether CSS transitions are disabled on the modal.
     *
     * @return bool True when transitions are suppressed
     */
    public function isDisableTransitions(): bool
    {
        return $this->uxOptionsResolver->isDisableTransitions();
    }

    /**
     * Returns whether iframe placeholders are managed after consent.
     *
     * @return bool True when iframe activation is enabled
     */
    public function isManageIframePlaceholders(): bool
    {
        return $this->uxOptionsResolver->isManageIframePlaceholders();
    }

    /**
     * Returns whether granular per-cookie selection is enabled.
     *
     * @return bool True when per-cookie toggles are shown
     */
    public function isGranularCookieSelection(): bool
    {
        return $this->uxOptionsResolver->isGranularCookieSelection();
    }

    /**
     * Returns whether the floating preferences bubble is enabled.
     *
     * @return bool True when the bubble button is rendered
     */
    public function isPreferencesBubbleEnabled(): bool
    {
        return $this->uxOptionsResolver->isPreferencesBubbleEnabled();
    }

    /**
     * Returns the screen corner used for the preferences bubble.
     *
     * @return string The configured bubble position
     */
    public function getPreferencesBubblePosition(): string
    {
        return $this->uxOptionsResolver->getPreferencesBubblePosition();
    }

    /**
     * Returns grouped preference sections for the modal.
     *
     * @return list<array<string, mixed>> Preference section definitions
     */
    public function getPreferenceSections(): array
    {
        return $this->uxOptionsResolver->getPreferenceSections();
    }
}
