<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Admin;

use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigAppearanceSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigBehaviorSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigConsentModalSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigPreferencesModalSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigProfileSettingsType;
use Nowo\CookieConsentBundle\Form\Settings\CookieConsentConfigRouteTargetingSettingsType;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Admin settings UI sections (route slugs + FormType class per tab).
 */
enum CookieConsentConfigSettingsSection: string
{
    case Profile          = 'profile';
    case Behavior         = 'behavior';
    case Appearance       = 'appearance';
    case ConsentModal     = 'consent-modal';
    case PreferencesModal = 'preferences-modal';
    case RouteTargeting   = 'route-targeting';

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
     * Symfony FormType FQCN for this section.
     *
     * @return class-string<FormTypeInterface<CookieConsentConfig>>
     */
    public function formType(): string
    {
        return match ($this) {
            self::Profile          => CookieConsentConfigProfileSettingsType::class,
            self::Behavior         => CookieConsentConfigBehaviorSettingsType::class,
            self::Appearance       => CookieConsentConfigAppearanceSettingsType::class,
            self::ConsentModal     => CookieConsentConfigConsentModalSettingsType::class,
            self::PreferencesModal => CookieConsentConfigPreferencesModalSettingsType::class,
            self::RouteTargeting   => CookieConsentConfigRouteTargetingSettingsType::class,
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
