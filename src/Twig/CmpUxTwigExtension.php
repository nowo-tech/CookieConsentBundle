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
    public function __construct(
        private readonly CmpUxOptionsResolver $uxOptionsResolver,
    ) {
    }

    /**
     * @return array<int, TwigFunction>
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

    public function isTwoStepModal(): bool
    {
        return $this->uxOptionsResolver->isTwoStepModal();
    }

    public function isOpenPreferencesModal(): bool
    {
        return $this->uxOptionsResolver->isOpenPreferencesModal();
    }

    public function getColorTheme(): string
    {
        return $this->uxOptionsResolver->getColorTheme();
    }

    public function isDarkModeEnabled(): bool
    {
        return $this->uxOptionsResolver->isDarkModeEnabled();
    }

    public function isDisableTransitions(): bool
    {
        return $this->uxOptionsResolver->isDisableTransitions();
    }

    public function isManageIframePlaceholders(): bool
    {
        return $this->uxOptionsResolver->isManageIframePlaceholders();
    }

    public function isGranularCookieSelection(): bool
    {
        return $this->uxOptionsResolver->isGranularCookieSelection();
    }

    public function isPreferencesBubbleEnabled(): bool
    {
        return $this->uxOptionsResolver->isPreferencesBubbleEnabled();
    }

    public function getPreferencesBubblePosition(): string
    {
        return $this->uxOptionsResolver->getPreferencesBubblePosition();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPreferenceSections(): array
    {
        return $this->uxOptionsResolver->getPreferenceSections();
    }
}
