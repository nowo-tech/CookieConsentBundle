<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Admin;

/**
 * Admin settings UI sections (route slugs + form field groups).
 */
enum CookieConsentConfigSettingsSection: string
{
    case Profile           = 'profile';
    case Behavior          = 'behavior';
    case Appearance        = 'appearance';
    case ConsentModal      = 'consent-modal';
    case PreferencesModal  = 'preferences-modal';
    case RouteTargeting    = 'route-targeting';

    /**
     * Translation key suffix under nowo_cookie_consent.admin.config.settings.section.*.
     */
    public function translationSuffix(): string
    {
        return match ($this) {
            self::Profile          => 'profile',
            self::Behavior         => 'behavior',
            self::Appearance       => 'appearance',
            self::ConsentModal     => 'consent_modal',
            self::PreferencesModal => 'preferences_modal',
            self::RouteTargeting   => 'route_targeting',
        };
    }

    /**
     * @return list<string> Symfony form field names for this section
     */
    public function formFields(): array
    {
        return match ($this) {
            self::Profile => [
                'enabled',
                'name',
                'routePatternsText',
                'priority',
                'default',
            ],
            self::Behavior => [
                'autoShow',
                'revision',
                'manageScriptTags',
                'autoClearCookies',
                'hideFromBots',
                'lazyHtmlGeneration',
                'manageIframePlaceholders',
                'granularCookieSelection',
                'preferencesBubbleEnabled',
                'preferencesBubblePosition',
                'preferencesBubbleBorderColor',
                'preferencesBubbleIcon',
            ],
            self::Appearance => [
                'colorTheme',
                'disablePageInteraction',
                'darkModeEnabled',
                'disableTransitions',
                'twoStepModal',
                'openPreferencesModal',
            ],
            self::ConsentModal => [
                'consentModalLayout',
                'consentModalVariant',
                'consentModalPositionY',
                'consentModalPositionX',
                'consentModalEqualWeightButtons',
                'consentModalFlipButtons',
            ],
            self::PreferencesModal => [
                'preferencesModalLayout',
                'preferencesModalVariant',
                'preferencesModalPositionY',
                'preferencesModalPositionX',
                'preferencesModalEqualWeightButtons',
                'preferencesModalFlipButtons',
            ],
            self::RouteTargeting => [
                'autoShowRouteMode',
                'autoShowRoutesText',
            ],
        };
    }

    /**
     * Route requirement pattern for {section}.
     */
    public static function routeRequirement(): string
    {
        return implode('|', array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        ));
    }
}
